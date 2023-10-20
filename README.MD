# WordpressBundler

<center>

[![Liqueur de Toile](https://hosting.liqueurdetoile.com/logo_lqdt.png "Liqueur de Toile website")](https://liqueurdetoile.com)

</center>

WordpressBundler is a wordpress project bundler written in PHP that produces clean production output. With WordpressBundler, you can easily keep all your PHP and JS code in a single repository, do your dev and, finally, creates a clean bundle that only embeds needed things for production.

- [WordpressBundler](#wordpressbundler)
  - [Why WordpressBundler ?](#why-wordpressbundler-)
  - [Install the bundler](#install-the-bundler)
  - [Running the bundler](#running-the-bundler)
    - [As a shell](#as-a-shell)
    - [Programmatically](#programmatically)
  - [Configuring bundler](#configuring-bundler)
    - [Configuration files](#configuration-files)
    - [Programmatic config](#programmatic-config)
  - [Bundler settings](#bundler-settings)
    - [Logging](#logging)
    - [Base path](#base-path)
    - [Finder](#finder)
    - [Composer](#composer)
    - [PHP-Scoper](#php-scoper)
    - [Output and temporary folders](#output-and-temporary-folders)
  - [Speeding up bundling](#speeding-up-bundling)
  - [Contributing or reporting bugs](#contributing-or-reporting-bugs)
  - [Changelog](#changelog)
  - [What's next ?](#whats-next-)

## Why WordpressBundler ?

In early years of PHP, all dynamic page rendering was done server side and, since there's no compiling step in PHP, the source code was also the production code.

By now, PHP and javascript are way more intricated. Moreover, dev code and dependencies are now widely spread though useless in production. On a badly configured server, they can even pose some security threats. Due to build step to bundle and optimize code, JS packages often rely on bundlers, like webpack for instance, to generate a production build.

For instance, say you're creating a wordpress plugin with scripts that needs a building step (like new Gutenberg blocks for instance). This can rapidly turn to a nightmare to organize. If you choose to have multiple repositories, you must take care of cascade updates to ensure that your plugin have the latest scripts versions. If using a monorepo, you'll embed a bunch of useless, and maybe dangerous, things in your plugin or theme. Furthermore, when using composer dependencies in a wordpress environment, you may have the wrong version loaded by another plugin and it breaks things apart.

**With WordpressBundler, you can easily keep all your PHP and JS code in a single monorepository, do your dev and, finally, creates a clean bundle that only embeds production outputs.**

Similarly to JS bundlers (like webpack), this tool takes care of the generation of the production version of a plugin or a theme as a folder or a ZIP archive, with or without composer dependencies bundled. It is also shipped with [`humbug/php-scoper`](https://github.com/humbug/php-scoper) great package to obfuscate dependencies namespaces and avoid conflicts.

## Install the bundler

```bash
composer require liqueurdetoile/wordpress-bundler --dev
```

## Running the bundler

With its default configuration the bundler will :

- Use the root of the project as base path
- Include all files and folders in your project root folder except VCS folders and those already excluded by VCS (Git, Mercurial...)
- Export these files as a zip archive to the `/dist` folder

If you only need to exclude some files or folders, simply create a `.wpignore` file at the root of your project and add to it the patterns to exclude. The syntax is exactly the same than a `.gitignore` file.

This default configuration is well fitted for projects that does not require any dependencies in bundle nor relies on composer autoloader for internal purposes.

> **Please read about bundler configuration just below for detailed configuration settings**

### As a shell

```bash
vendor/bin/wpbundler bundle
```

There's a few options available :

- `--config <file>` or `-c <file>` : Requests loading of additional configuration stored in a file. You can load multiple configurations in this way.
- `--log <1-7>` or `-l <1-7>` : Overrides bundler logging configuration and enable log with the given level.
- `--dry` : Requests a dry run
- `--show-entries-only` : Bundler will output entries that will be bundled based on its configuration

### Programmatically

Simply create an instance of `\Lqdt\WordpressBundler\Bundler` and call its `bundle` method. See below how to customize configuration.

## Configuring bundler

### Configuration files

The easiest way to tweak bundler configuration est to set up a configuration file. As default the bundler will always look first into `extra.wpbundler` key in project `composer.json` to fetch custom configuration. Alternatively, you can create an external file to store settings and calls it with `--config` option of the command line or, if used programmatically, with `Bundler::loadConfigFile` method.

To ensure config integrity, additional configuration is always **merged** with current one. Therefore, only updated keys of settings have to be provided.

To ease process of creating donfiguration file You can initialize a default config to be tweaked with this command :

```bash
# Generates config at extra.wpbundler key in project composer.json
vendor/bin/wpbundler init

# Generates an external file
vendor/bin/wpbundler init -t bundler.config.php

# Generates an external file and will stores config at bundler key in that file
vendor/bin/wpbundler init -t config.json -k bundler
```

Config file language is inferred from its extension and can be any of PHP (.php), JSON (.json), XML (.xml), INI, (.ini) or YAML (.yml). YAML requires PECL module `yaml` to be loaded.

### Programmatic config

You can simply provide a custom configuration to bundler constructor or use `Bundler::setConfig`. Current configuration can be rerieved through `Bundler::getConfig` method.

## Bundler settings

Available options for configuration with their default values are as following :

```php
[
    'log' => true,
    'loglevel' => 5,
    'basepath' => null,
    'finder' => [
        'exclude' => [],
        'depth' => 0,
        'ignoreDotFiles' => true,
        'ignoreVCS' => true,
        'ignoreVCSIgnored' => true,
    ],
    'composer' => [
        'install' => false,
        'dev-dependencies' => false,
        'phpscoper' => false,
    ],
    'tmpdir' => null,
    'output' => 'dist',
    'clean' => true,
    'zip' => 'bundle.zip',
];
```

### Logging

The bundler can outputs logs to console based on its parameters. It relies on `laminas/laminas-log` package.

If `log` option is set to false, no log will be outputted at all. `loglevel` option is following BSD syslog protocol and expects `int` values between 0 to 7. See [Laminas log](https://docs.laminas.dev/laminas-log/intro/#using-built-in-priorities) for more informations.

> **NOTE** : You can access and change bundler logger instance programmatically through `Bundler::getLogger` and `Bundler::setLogger` methods.

### Base path

Base path is used by the bundler to resolve any relative path stored in settings. Bundler base path is automatically set to current project root folder that will fit most of use cases.

If needed, you can specify a different base path for the bundler. If a relative path is provided, it will be resolved from project root folder.

### Finder

The bundler embeds `symfony/finder` package to analyze filesystem and extract entries to be bundled. With default settings, all files and folders located in bundler base path will be integrated into the bundle, except VCS own and ignored files and folders.

Available settings under `finder`settings key are :

- `exclude` : You can provide here some patterns to be excluded. They will be used to add [`notName`](https://symfony.com/doc/current/components/finder.html#file-name) and [`notPath`](https://symfony.com/doc/current/components/finder.html#path) constraints to the finder
- `depth` : [See Finder component documentation](https://symfony.com/doc/current/components/finder.html#directory-depth). To disable depth setting, simply pass `-1` as value.
- `ignoreDotFiles` : [See Finder component documentation](https://symfony.com/doc/current/components/finder.html#version-control-files)
- `ignoreVCS` : [See Finder component documentation](https://symfony.com/doc/current/components/finder.html#version-control-files)
- `ignoreVCSIgnored` : [See Finder component documentation](https://symfony.com/doc/current/components/finder.html#version-control-files)

**I deliberately limit depth to 0 in order to speed up things as copying a whole folder is way more quicker than copying each of its files and subfolders.** See [Speeding up bundling](#speeding-up-bundling) for more informations.

The actual options can handle 99% of needs but there's some limitations as Finder Component cannot exclude files based on path for instance. Please refer to [component own documentation](https://symfony.com/doc/current/components/finder.html#usage) for more details.

> **NOTE** : You can access and change bundler finder instance programmatically through `Bundler::getFinder` and `Bundler::setFinder` methods.

### Composer

You can tell bundler to perform a clean install before bundling to embeds dependencies in final bundle and allow use of Composer autoloader. If you're using external dependencies, it is recommended to also use [PHP-Scoper](#php-scoper) to avoid conflicts of package versions between plugins or themes in a wordpress deployment.

Available settings under `composer` settings key are :

- `install` : If `true`, bundler will run `composer install` in exported bundle.
- `dev-dependencies` : If `true`, bundler will also includes dev dependencies in exported bundle.
- `phpscoper` : See [next section](#php-scoper)

### PHP-Scoper

The package [`humbug/php-scoper`](https://github.com/humbug/php-scoper) provides a convenient way to avoid conflicts of package versions between plugins or themes in a wordpress deployment. It will update all namespaces and bundler will take care of updating autoloader.

If you want to use a custom configuration you can create a [`scoper.inc.php`](https://github.com/humbug/php-scoper/blob/main/docs/configuration.md#configuration) file in bundler base path that will be automatically loaded.

### Output and temporary folders

Available settings are :

- `tmpdir` : As default, the bundler use `sys_get_temp_dir` built-in PHP function to locate a root folde for its own temporary folders. You can alternatively provide an **absolute** path to another folder
- `output` : Path to bundle output folder. If relative, it will be resolved from bundler base path
- `clean` : If `true`, output folder will be emptied at each bundle start
- `zip` : If `true`, all bundle entries are gathered in a single ZIP archive named from this option. Extension in name optional.

## Speeding up bundling

There's a few things you can do to improve the bundling speed :

1. Keep your files well structured in order to handle filtering patterns at root level only. Copying whole folders are **way more faster** than processing each nested files
2. If you're not using composer autoloader, check that install step is disabled by setting `composer.install` to `false` and filter out any composer/dev files and folders
3. If you're only using your own package and namespace and ensuring it won't conflict with others (with vendor prefix for instance), there's no need to use `php-scoper`

## Contributing or reporting bugs

WordpressBundler is welcoming any contribution and related PRs. Pleasy follow the contributing guidelines to do so.

Any bug or suggestion should be made in this repository issues tracker.

## Changelog

- 1.0.0 : Initial release

## What's next ?

There's many things that can be done to improve Wordpress Bundler. For instance, add a `check` command to verify if a pattern is found or not based on bundler configuration.

Feel free to suggest improvements and/or submit PRs !
