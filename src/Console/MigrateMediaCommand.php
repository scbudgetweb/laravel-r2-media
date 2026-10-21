<?php

namespace Scbudgetweb\R2Media\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Scbudgetweb\R2Media\R2Media;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;
use Throwable;

/**
 * Copies existing Spatie media (originals, conversions and responsive images)
 * to another disk and repoints each media row once its files are copied.
 *
 * Files are streamed, so large videos never load fully into memory. Source
 * files are left in place; delete them manually once the new disk is verified.
 */
class MigrateMediaCommand extends Command
{
    protected $signature = 'r2-media:migrate
        {--to= : Target disk (defaults to the configured media disk)}
        {--from= : Only migrate media currently on this disk}
        {--dry-run : List what would be copied without copying anything}';

    protected $description = 'Copy existing media files to the configured media disk and update their media records';

    public function handle(): int
    {
        $to = $this->option('to') ?: R2Media::disk();
        $from = $this->option('from');
        $isDryRun = (bool) $this->option('dry-run');

        foreach (array_filter([$to, $from]) as $disk) {
            if (config("filesystems.disks.{$disk}") === null) {
                $this->components->error("Disk [{$disk}] is not configured.");

                return self::FAILURE;
            }
        }

        $query = $this->pendingMediaQuery($to, $from);
        $total = $query->count();

        if ($total === 0) {
            $this->components->info("Nothing to migrate. All media is already on [{$to}].");

            return self::SUCCESS;
        }

        $this->components->info(($isDryRun ? '[Dry run] ' : '')."Migrating {$total} media item(s) to [{$to}].");

        $migrated = 0;
        $fileCount = 0;
        $failures = [];

        $query->chunkById(100, function ($mediaItems) use ($to, $isDryRun, &$migrated, &$fileCount, &$failures) {
            foreach ($mediaItems as $media) {
                try {
                    $files = $this->filesFor($media);
                } catch (Throwable $exception) {
                    $failures[] = [$media->getKey(), $media->file_name, $exception->getMessage()];

                    continue;
                }

                $originalPath = PathGeneratorFactory::create($media)->getPath($media).$media->file_name;

                if (! in_array([$media->disk, $originalPath], $files, true)) {
                    $failures[] = [$media->getKey(), $media->file_name, __('r2-media::r2-media.migrate.missing_original')];

                    continue;
                }

                if ($isDryRun) {
                    $this->line(sprintf('  #%s %s: %d file(s) %s → %s', $media->getKey(), $media->file_name, count($files), $media->disk, $to));
                    $fileCount += count($files);
                    $migrated++;

                    continue;
                }

                $error = $this->copyFiles($files, $to);

                if ($error !== null) {
                    $failures[] = [$media->getKey(), $media->file_name, $error];

                    continue;
                }

                $media->forceFill(['disk' => $to, 'conversions_disk' => $to])->saveQuietly();

                $fileCount += count($files);
                $migrated++;
            }
        });

        $this->newLine();
        $this->components->twoColumnDetail($isDryRun ? 'Would migrate' : 'Migrated', "{$migrated} media item(s), {$fileCount} file(s)");
        $this->components->twoColumnDetail('Failed', (string) count($failures));

        if ($failures !== []) {
            $this->table(['Media ID', 'File', 'Reason'], $failures);

            return self::FAILURE;
        }

        if (! $isDryRun) {
            $this->components->info('Source files were left in place. Remove them once the new disk is verified.');
        }

        return self::SUCCESS;
    }

    /**
     * @return Builder<Media>
     */
    private function pendingMediaQuery(string $to, ?string $from): Builder
    {
        /** @var class-string<Media> $mediaModel */
        $mediaModel = config('media-library.media_model', Media::class);

        return $mediaModel::query()
            ->where(function (Builder $query) use ($to) {
                $query->where('disk', '!=', $to)
                    ->orWhere(fn (Builder $query) => $query->whereNotNull('conversions_disk')->where('conversions_disk', '!=', $to));
            })
            ->when($from, fn (Builder $query) => $query->where('disk', $from));
    }

    /**
     * Every file belonging to a media item, as [disk, path] pairs.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function filesFor(Media $media): array
    {
        $pathGenerator = PathGeneratorFactory::create($media);
        $conversionsDisk = $media->conversions_disk ?: $media->disk;

        $directories = [
            [$media->disk, $pathGenerator->getPath($media)],
            [$conversionsDisk, $pathGenerator->getPathForConversions($media)],
            [$conversionsDisk, $pathGenerator->getPathForResponsiveImages($media)],
        ];

        $files = [];

        foreach ($directories as [$disk, $directory]) {
            foreach (Storage::disk($disk)->allFiles(rtrim($directory, '/')) as $path) {
                $files["{$disk}|{$path}"] = [$disk, $path];
            }
        }

        return array_values($files);
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $files
     * @return string|null Error message, or null when every file copied.
     */
    private function copyFiles(array $files, string $to): ?string
    {
        $target = Storage::disk($to);

        foreach ($files as [$disk, $path]) {
            if ($disk === $to) {
                continue;
            }

            $error = $this->copyFile(Storage::disk($disk), $target, $path);

            if ($error !== null) {
                return $error;
            }
        }

        return null;
    }

    private function copyFile(Filesystem $source, Filesystem $target, string $path): ?string
    {
        try {
            $stream = $source->readStream($path);

            if (! is_resource($stream)) {
                return __('r2-media::r2-media.migrate.read_failed', ['path' => $path]);
            }

            $written = $target->writeStream($path, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }

            return $written ? null : __('r2-media::r2-media.migrate.write_failed', ['path' => $path]);
        } catch (Throwable $exception) {
            return "{$path}: {$exception->getMessage()}";
        }
    }
}
