<?php

declare(strict_types=1);

namespace Simsoft\DataFlow\Traits;

/**
 * ResolvesOutputPath trait.
 *
 * Splits a destination path into its base path and file extension for
 * spreadsheet loaders, which append a timestamp between the two.
 */
trait ResolvesOutputPath
{
    /**
     * Split a destination path into base path and extension.
     *
     * Only an extension on the final path segment counts. Directory names
     * containing dots (`releases/v1.2/report.xlsx`, or a Windows profile path
     * such as `C:\Users\jane.doe\out`) are left intact — splitting on the first
     * dot would truncate the directory and write to the wrong location.
     *
     * A path with no extension keeps the supplied default, so
     * `output/report` stays `output/report` with the caller's default type.
     *
     * @param string $path The destination path.
     * @param string $defaultExtension Extension to use when the path has none.
     *
     * @return array{0: string, 1: string} The base path and the extension.
     */
    protected function splitOutputPath(string $path, string $defaultExtension): array
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        if ($extension === '') {
            return [$path, $defaultExtension];
        }

        return [substr($path, 0, -(strlen($extension) + 1)), $extension];
    }
}
