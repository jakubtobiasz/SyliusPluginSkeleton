<?php

function ask(string $question, string $default = ''): string
{
    $answer = readline($question.($default ? " ({$default})" : null).': ');

    if (! $answer) {
        return $default;
    }

    return $answer;
}

function confirm(string $question, bool $default = false): bool
{
    $answer = ask($question.' ('.($default ? 'Y/n' : 'y/N').')');

    if (! $answer) {
        return $default;
    }

    return strtolower($answer) === 'y';
}

function writeln(string $line): void
{
    echo $line.PHP_EOL;
}

function run(string $command): string
{
    return trim((string) shell_exec($command));
}

function slugify(string $subject): string
{
    return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $subject));
}

function title_snake(string $subject, string $replace = '_'): string
{
    return str_replace(['-', '_'], $replace, $subject);
}

function replace_in_file(string $file, array $replacements): void
{
    $contents = file_get_contents($file);

    file_put_contents(
        $file,
        str_replace(
            array_keys($replacements),
            array_values($replacements),
            $contents
        )
    );
}

function remove_composer_deps(array $names): void
{
    $data = json_decode(file_get_contents(__DIR__ . '/composer.json'), true);

    if (!isset($data['require-dev']) || !is_array($data['require-dev'])) {
        return;
    }

    foreach ($data['require-dev'] as $name => $version) {
        if (in_array($name, $names, true)) {
            unset($data['require-dev'][$name]);
        }
    }

    file_put_contents(__DIR__ . '/composer.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function remove_composer_script(string $scriptName): void
{
    /** @var string $composerJson */
    $composerJson = file_get_contents(__DIR__ . '/composer.json');
    /** @var array{scripts: array<string, string|array>} $data */
    $data = json_decode($composerJson, true);

    if (!isset($data['scripts']) || !is_array($data['scripts'])) {
        return;
    }

    foreach ($data['scripts'] as $name => $script) {
        if (is_array($script) && in_array("@{$scriptName}", $script, true)) {
            $data['scripts'][$name] = array_filter($data['scripts'][$name], fn (string $script) => $script !== "@{$scriptName}");
            $data['scripts'][$name] = array_values($data['scripts'][$name]);
        }

        if ($scriptName === $name) {
            unset($data['scripts'][$name]);
        }
    }

    file_put_contents(__DIR__ . '/composer.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function remove_readme_paragraphs(string $file): void
{
    $contents = file_get_contents($file);

    file_put_contents(
        $file,
        preg_replace('/<!--delete-->.*<!--\/delete-->/s', '', $contents) ?: $contents
    );
}

function safeUnlink(string $filename): void
{
    if (file_exists($filename) && is_file($filename)) {
        unlink($filename);
    }
}

function deleteRecursively(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    $projectPath = __DIR__;
    if (!str_starts_with(realpath($path), realpath($projectPath))) {
        echo "Warning: Attempted to delete a file outside the project path: " . realpath($path) . PHP_EOL;
        return;
    }

    if (is_file($path)) {
        unlink($path);
        return;
    }

    $dir = new DirectoryIterator($path);
    foreach ($dir as $item) {
        if (!$item->isDot()) {
            deleteRecursively($item->getPathname());
        }
    }
    rmdir($path);
}

function determineSeparator(string $path): string
{
    return str_replace('/', DIRECTORY_SEPARATOR, $path);
}

function replaceForWindows(): array
{
    return preg_split('/\\r\\n|\\r|\\n/', run('dir /S /B * | findstr /v /i .git\ | findstr /v /i vendor | findstr /v /i '.basename(__FILE__).' | findstr /r /i /M /F:/ ":author_email :author_name :config_key :extension_class :full_namespace :full_namespace_double_backslash :package_description :package_name :plugin_class"'));
}

function replaceForAllOtherOSes(): array
{
    return explode(PHP_EOL, run('grep -E -r -l -i ":author_email|:author_name|:config_key|:extension_class|:full_namespace|:full_namespace_double_backslash|:package_description|:package_name|:plugin_class" --exclude-dir=vendor ./* ./.github/* | grep -v '.basename(__FILE__)));
}

$gitName = run('git config user.name');
$authorName = ask('Author name', $gitName);

$gitEmail = run('git config user.email');
$authorEmail = ask('Author email', $gitEmail);

$vendorName = ask('Vendor name', 'Acme');
$vendorNameSlug = slugify($vendorName);
$pluginName = ask('Plugin name', 'SyliusExamplePlugin');
$pluginNameSlug = slugify($pluginName);

$packageName = ask('Package name', "{$vendorNameSlug}/{$pluginNameSlug}");

$packageDescription = ask('Package description', "This is my brand new Sylius plugin.");

$fullNamespace = "{$vendorName}\\{$pluginName}";
$fullNamespaceDoubleBackslash = "{$vendorName}\\\\{$pluginName}";
$pluginClass = "{$vendorName}{$pluginName}";
$configKey = title_snake(str_replace('/', '-', $packageName));
$extensionClass = str_replace('Plugin', 'Extension', $pluginClass);

$useDocker = confirm('Use Docker?', true);
$usePsalm = confirm('Use Psalm?', true);
$usePhpStan = confirm('Use PHPStan?', true);
$useEcs = confirm('Use ECS?', true);
$usePhpUnit = confirm('Use PHPUnit?', true);
$usePhpSpec = confirm('Use PHPSpec?', true);
$useBehat = confirm('Use Behat?', true);

writeln('------');
writeln("Author       : {$authorName}, <{$authorEmail}>");
writeln("Vendor       : {$vendorName}");
writeln("Plugin       : {$pluginName}");
writeln("Package name : {$packageName} <{$packageDescription}>");
writeln("Namespace    : {$fullNamespace}");
writeln('---');
writeln('Packages & Utilities');
writeln('Use Docker       : '.($useDocker ? 'yes' : 'no'));
writeln('Use Psalm        : '.($usePsalm ? 'yes' : 'no'));
writeln('Use PHPStan      : '.($usePhpStan ? 'yes' : 'no'));
writeln('Use ECS          : '.($useEcs ? 'yes' : 'no'));
writeln('Use PHPUnit      : '.($usePhpUnit ? 'yes' : 'no'));
writeln('Use PHPSpec      : '.($usePhpSpec ? 'yes' : 'no'));
writeln('Use Behat        : '.($useBehat ? 'yes' : 'no'));
writeln('------');

writeln('This script will replace the above values in all relevant files in the project directory.');

if (! confirm('Modify files?', true)) {
    exit(1);
}

$files = (str_starts_with(strtoupper(PHP_OS), 'WIN') ? replaceForWindows() : replaceForAllOtherOSes());

safeUnlink(__DIR__ . '/composer.json');

foreach ($files as $file) {
    replace_in_file($file, [
        ':author_email' => $authorEmail,
        ':author_name' => $authorName,
        ':config_key' => $configKey,
        ':extension_class' => $extensionClass,
        ':full_namespace_double_backslash' => $fullNamespaceDoubleBackslash,
        ':full_namespace' => $fullNamespace,
        ':package_description' => $packageDescription,
        ':package_name' => $packageName,
        ':plugin_class' => $pluginClass,
    ]);

    match (true) {
        str_contains($file, determineSeparator('composer.template.json')) => rename($file, determineSeparator('composer.json')),
        str_contains($file, determineSeparator('src/Plugin.php')) => rename($file, determineSeparator('./src/'.$pluginClass.'.php')),
        str_contains($file, determineSeparator('src/DependencyInjection/Extension.php')) => rename($file, determineSeparator('./src/DependencyInjection/'.$extensionClass.'.php')),
        str_contains($file, 'README.md') => remove_readme_paragraphs($file),
        default => [],
    };
}

if (false === $useDocker) {
    safeUnlink(__DIR__ . '/.docker/nginx/nginx.conf');
    safeUnlink(__DIR__ . '/.docker/php/php.ini');
    safeUnlink(__DIR__ . '/docker-compose.yml');
    deleteRecursively(__DIR__ . '/.docker');
}

if (false === $usePsalm) {
    safeUnlink(__DIR__ . '/psalm.xml');
    remove_composer_deps(['vimeo/psalm']);
    remove_composer_script('psalm');
}

if (false === $usePhpStan) {
    safeUnlink(__DIR__ . '/phpstan.neon');
    remove_composer_deps([
        'phpstan/extension-installer',
        'phpstan/phpstan',
        'phpstan/phpstan-doctrine',
        'phpstan/phpstan-strict-rules',
        'phpstan/phpstan-webmozart-assert',
    ]);
    remove_composer_script('phpstan');
}

if (false === $useEcs) {
    safeUnlink(__DIR__ . '/ecs.php');
    remove_composer_deps(['sylius-labs/coding-standard']);
    remove_composer_script('ecs');
}

if (false === $usePhpUnit) {
    safeUnlink(__DIR__ . '/phpunit.xml.dist');
    remove_composer_deps(['phpunit/phpunit']);
    remove_composer_script('phpunit');
}

if (false === $usePhpSpec) {
    safeUnlink(__DIR__ . '/phpspec.yml.dist');
    deleteRecursively(__DIR__ . '/spec');
    remove_composer_deps(['phpspec/phpspec']);
    remove_composer_script('phpspec');
}

if (false === $useBehat) {
    safeUnlink(__DIR__ . '/behat.yml.dist');
    deleteRecursively(__DIR__ . '/etc');
    deleteRecursively(__DIR__ . '/features');
    deleteRecursively(__DIR__ . '/tests/Behat');
    remove_composer_deps([
        "behat/behat",
        "behat/mink-selenium2-driver",
        "dmore/behat-chrome-extension",
        "dmore/chrome-mink-driver",
        "friends-of-behat/mink",
        "friends-of-behat/mink-browserkit-driver",
        "friends-of-behat/mink-debug-extension",
        "friends-of-behat/mink-extension",
        "friends-of-behat/page-object-extension",
        "friends-of-behat/suite-settings-extension",
        "friends-of-behat/symfony-extension",
        "friends-of-behat/variadic-extension",
    ]);
}

if (false === $usePhpUnit && false === $useBehat) {
    remove_composer_deps(['symfony/browser-kit']);
}

$runSetup = confirm('Execute `composer install` and run tests?') ;

if ($runSetup) {
    run('composer install');
    run('php tests/Application/bin/console doctrine:database:create -e test');
    run('php tests/Application/bin/console doctrine:schema:update -e test -f');
    run('composer ci');
}

$autoRemove = confirm('Let this script delete itself?', true);

if ($autoRemove) {
    unlink(__FILE__);
    remove_composer_script('configure');
}
