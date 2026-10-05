<?php

namespace App\Services\Documents;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

/** New uploads stay outside the web root; retained public files remain readable through authorization. */
class PrivateFileStorage
{
    private const PRIVATE_FOLDERS = ['notification', 'production', 'employee', 'sale_agent', 'supplier'];

    public function store(UploadedFile $upload, string $folder): string
    {
        if (!in_array($folder, self::PRIVATE_FOLDERS, true)) {
            throw new RuntimeException('Unsupported private upload folder.');
        }
        $directory = storage_path('app/private/files/'.$folder);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Cannot create private upload directory.');
        }
        // Neither a folder link nor an ancestor link may redirect a write outside private storage.
        $anchor = realpath(storage_path('app'));
        if (!$this->within(realpath($directory), $anchor)
            || $this->normalize(realpath($directory)) !== $this->normalize($anchor.'/private/files/'.$folder)) {
            throw new RuntimeException('Private upload directory escapes storage.');
        }
        $file = Str::uuid().'.'.strtolower($upload->getClientOriginalExtension());
        $upload->move($directory, $file);

        return $file;
    }

    public function path(string $folder, string $file): ?string
    {
        if (!$this->validFilename($file) || !preg_match('/^[a-z_-]+$/D', $folder)) {
            return null;
        }
        $privateRoot = storage_path('app/private/files');
        $path = $this->containedFile($privateRoot, $folder.'/'.$file, storage_path('app'));
        if ($path !== null) {
            return $path;
        }
        $family = in_array($folder, ['employee', 'sale_agent', 'supplier'], true) ? 'images' : 'documents';

        $path = $this->containedFile(public_path($family), $folder.'/'.$file, public_path());
        // Older sale-agent creation stored employee portraits in a second directory.
        return $path ?? ($folder === 'employee'
            ? $this->containedFile(public_path('images'), 'sale_agent/'.$file, public_path()) : null);
    }

    public function delete(string $folder, ?string $file): void
    {
        if ($file && ($path = $this->path($folder, $file))) {
            unlink($path);
        }
    }

    public function validFilename(string $file): bool
    {
        return $file !== '' && !in_array($file, ['.', '..'], true)
            && !preg_match('/[\\\\\/\x00-\x1f\x7f%]/', $file) && basename($file) === $file;
    }

    private function containedFile(string $root, string $relative, string $anchor): ?string
    {
        $resolvedRoot = realpath($root);
        $path = realpath($root.'/'.$relative);
        // Check the root against its trusted anchor too: a linked family directory cannot escape it.
        return $this->within($resolvedRoot, realpath($anchor)) && $this->within($path, $resolvedRoot)
            && $this->normalize($path) === $this->normalize($resolvedRoot.'/'.$relative)
            && ($root !== storage_path('app/private/files')
                || $this->normalize($resolvedRoot) === $this->normalize(realpath($anchor).'/private/files'))
            && is_file($path) ? $path : null;
    }

    private function within(string|false $path, string|false $root): bool
    {
        if ($path === false || $root === false) {
            return false;
        }
        return str_starts_with($this->normalize($path), rtrim($this->normalize($root), '/').'/');
    }

    private function normalize(string|false $path): string
    {
        $path = str_replace('\\', '/', $path ?: '');
        return PHP_OS_FAMILY === 'Windows' ? strtolower($path) : $path;
    }
}
