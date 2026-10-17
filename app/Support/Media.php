<?php

namespace App\Support;

use App\Models\Book;
use App\Models\ExamSet;
use App\Models\Paper;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * The image library: book covers (img/books), paper sheets (img/papers, also used by exam set previews) and the brand
 * files in img/. Files live under config('wakata.image_dir') (public/assets/img) and are referenced from the database
 * by a path relative to that folder, e.g. books/biology-uce.jpg.
 */
class Media
{
    public const KINDS = ['books', 'papers'];

    /** Files that belong to the site itself and can never be deleted or replaced from the library. */
    public const BRAND = ['logo.png', 'logo-white.png', 'favicon.png'];

    private const EXT = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    public static function dir(): string
    {
        return rtrim(config('wakata.image_dir'), '/\\');
    }

    /** A path coming from the browser is only accepted in the shape books/x.jpg or papers/x.png. */
    public static function validPath(?string $path): bool
    {
        return is_string($path) && (bool) preg_match('#^(books|papers)/[A-Za-z0-9._-]+\.(jpe?g|png|webp|gif)$#i', $path);
    }

    public static function exists(string $path): bool
    {
        return (self::validPath($path) || in_array($path, self::BRAND, true)) && is_file(self::dir() . '/' . $path);
    }

    /**
     * Every image with the places it is used. `used` lists human-readable uses, `refs` how many records point at it.
     *
     * @return array<int, array{path:string,name:string,kind:string,size:int,use:string,refs:int,url:string,locked:bool}>
     */
    public static function files(): array
    {
        $uses = self::usage();
        $out  = [];

        foreach (self::KINDS as $kind) {
            foreach (self::scan(self::dir() . '/' . $kind) as $file) {
                $path = $kind . '/' . $file;
                $out[] = self::row($path, $kind, $uses[$path] ?? []);
            }
        }
        foreach (self::BRAND as $file) {
            if (is_file(self::dir() . '/' . $file)) {
                $label = ['logo.png' => 'Site logo', 'logo-white.png' => 'Logo for dark backgrounds', 'favicon.png' => 'Browser tab icon'][$file];
                $out[] = self::row($file, 'brand', [$label]) + [];
            }
        }

        return $out;
    }

    /** Saves one uploaded image into books/ or papers/ and returns its relative path. */
    public static function store(UploadedFile $file, string $kind): string
    {
        $kind = in_array($kind, self::KINDS, true) ? $kind : 'books';
        $ext  = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
        $ext  = $ext === 'jpeg' ? 'jpg' : $ext;
        $ext  = in_array($ext, self::EXT, true) ? $ext : 'jpg';

        $base = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'image';
        $name = Str::limit($base, 60, '') . '-' . Str::lower(Str::random(6)) . '.' . $ext;

        File::ensureDirectoryExists(self::dir() . '/' . $kind);
        $file->move(self::dir() . '/' . $kind, $name);

        return $kind . '/' . $name;
    }

    /** Overwrites an image in place (same path, so every page using it shows the new picture). */
    public static function replace(string $path, UploadedFile $file): void
    {
        $ext = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
        $ext = $ext === 'jpeg' ? 'jpg' : $ext;
        $cur = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $cur = $cur === 'jpeg' ? 'jpg' : $cur;

        if ($ext !== $cur) {
            throw new \InvalidArgumentException('Upload a ' . strtoupper($cur) . ' file to replace this one, or upload the new file as a new image.');
        }

        $file->move(dirname(self::dir() . '/' . $path), basename($path));
    }

    /**
     * Deletes an image. Book and paper records that used it simply lose the picture (the shop draws a cover instead).
     * An image that is part of an exam set preview cannot be deleted: change the set first.
     */
    public static function delete(string $path): void
    {
        if (! self::validPath($path) || ! is_file(self::dir() . '/' . $path)) {
            throw new \InvalidArgumentException('That file does not exist.');
        }
        if (self::inSetPreview($path)) {
            throw new \InvalidArgumentException('This image is used in an exam set preview. Change that set first, then delete it.');
        }

        foreach (Book::where('cover_image', $path)->get() as $b) {
            $b->update(['cover_image' => null]);
        }
        foreach (Book::where('back_image', $path)->get() as $b) {
            $b->update(['back_image' => null]);
        }
        foreach (Paper::where('cover_image', $path)->get() as $p) {
            $p->update(['cover_image' => null]);
        }

        File::delete(self::dir() . '/' . $path);
        Catalogue::flush();
    }

    private static function inSetPreview(string $path): bool
    {
        if (! str_starts_with($path, 'papers/')) {
            return false;
        }
        $name = basename($path);

        return ExamSet::all()->contains(fn (ExamSet $s) => in_array($name, $s->previews ?? [], true));
    }

    /** @return array<string, array<int, string>>  path => ["Cover: Biology ...", ...] */
    private static function usage(): array
    {
        $uses = [];
        foreach (Book::select('title', 'cover_image', 'back_image')->get() as $b) {
            if ($b->cover_image) {
                $uses[$b->cover_image][] = 'Cover: ' . $b->title;
            }
            if ($b->back_image) {
                $uses[$b->back_image][] = 'Back or inside page: ' . $b->title;
            }
        }
        foreach (Paper::select('title', 'year', 'cover_image')->get() as $p) {
            if ($p->cover_image) {
                $uses[$p->cover_image][] = 'Past paper sheet: ' . $p->title . ' ' . $p->year;
            }
        }
        foreach (ExamSet::select('title', 'previews')->get() as $s) {
            foreach ($s->previews ?? [] as $name) {
                $uses['papers/' . $name][] = 'Exam set preview: ' . $s->title;
            }
        }

        return $uses;
    }

    private static function row(string $path, string $kind, array $uses): array
    {
        $full = self::dir() . '/' . $path;

        return [
            'path'   => $path,
            'name'   => basename($path),
            'kind'   => $kind,
            'size'   => max(1, (int) round((is_file($full) ? filesize($full) : 0) / 1024)),
            'use'    => $uses ? implode('; ', array_slice($uses, 0, 3)) . (count($uses) > 3 ? ' and ' . (count($uses) - 3) . ' more' : '') : 'Not used yet',
            'refs'   => $kind === 'brand' ? 1 : count($uses),
            'url'    => Catalogue::imageUrl($path),
            'locked' => $kind === 'brand',
        ];
    }

    /** @return array<int, string> */
    private static function scan(string $dir): array
    {
        if (! is_dir($dir)) {
            return [];
        }
        $files = array_values(array_filter(scandir($dir) ?: [], fn ($f) => $f[0] !== '.' && preg_match('/\.(jpe?g|png|webp|gif)$/i', $f)));
        sort($files, SORT_NATURAL | SORT_FLAG_CASE);

        return $files;
    }
}
