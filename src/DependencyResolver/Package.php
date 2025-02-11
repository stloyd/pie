<?php

declare(strict_types=1);

namespace Php\Pie\DependencyResolver;

use Composer\Package\CompletePackageInterface;
use Php\Pie\ConfigureOption;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;

use function array_key_exists;
use function array_map;
use function array_slice;
use function explode;
use function implode;
use function parse_url;
use function str_contains;
use function str_starts_with;

/**
 * @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks
 *
 * @immutable
 */
final class Package
{
    /** @param list<ConfigureOption> $configureOptions */
    public function __construct(
        public readonly CompletePackageInterface $composerPackage,
        public readonly ExtensionType $extensionType,
        public readonly ExtensionName $extensionName,
        public readonly string $name,
        public readonly string $version,
        public readonly string|null $downloadUrl,
        public readonly array $configureOptions,
        public readonly bool $supportZts,
        public readonly bool $supportNts,
    ) {
    }

    public static function fromComposerCompletePackage(CompletePackageInterface $completePackage): self
    {
        $phpExtOptions = $completePackage->getPhpExt();

        $configureOptions = $phpExtOptions !== null && array_key_exists('configure-options', $phpExtOptions)
            ? array_map(
                static fn (array $configureOption): ConfigureOption => ConfigureOption::fromComposerJsonDefinition($configureOption),
                $phpExtOptions['configure-options'],
            )
            : [];

        $supportZts = $phpExtOptions !== null && array_key_exists('support-zts', $phpExtOptions)
            ? $phpExtOptions['support-zts']
            : true;

        $supportNts = $phpExtOptions !== null && array_key_exists('support-nts', $phpExtOptions)
            ? $phpExtOptions['support-nts']
            : true;

        return new self(
            $completePackage,
            ExtensionType::tryFrom($completePackage->getType()) ?? ExtensionType::PhpModule,
            ExtensionName::determineFromComposerPackage($completePackage),
            $completePackage->getPrettyName(),
            $completePackage->getPrettyVersion(),
            $completePackage->getDistUrl(),
            $configureOptions,
            $supportZts,
            $supportNts,
        );
    }

    public function prettyNameAndVersion(): string
    {
        return $this->name . ':' . $this->version;
    }

    public function githubOrgAndRepository(): string
    {
        if ($this->downloadUrl === null || str_contains($this->downloadUrl, '/' . $this->name . '/')) {
            return $this->name;
        }

        if (! str_starts_with($this->downloadUrl, 'https://api.github.com/repos/')) {
            return $this->name;
        }

        $parsed = parse_url($this->downloadUrl);
        if ($parsed === false || ! array_key_exists('path', $parsed)) {
            return $this->name;
        }

        // Converts https://api.github.com/repos/<user>/<repository>/zipball/<sha>" to "<user>/<repository>"
        return implode('/', array_slice(explode('/', $parsed['path']), 2, 2));
    }
}
