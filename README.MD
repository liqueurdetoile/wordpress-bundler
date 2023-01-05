# WordpressBundler

WordpressBundler is a wordpress project bundler written in PHP that produces clean production output. With WordpressBundler, you can easily keep all your PHP and JS code in a single repository, do your dev and, finally, creates a clean bundle that only embeds needed things for production.

- [WordpressBundler](#wordpressbundler)
  - [Why WordpressBundler ?](#why-wordpressbundler-)
  - [Install the bundler](#install-the-bundler)
  - [How bundling works ?](#how-bundling-works-)
  - [Running bundler](#running-bundler)
    - [As a shell](#as-a-shell)
    - [Programmatically](#programmatically)
  - [Bundler settings](#bundler-settings)
  - [How to configure the bundler](#how-to-configure-the-bundler)
    - [Understanding bundler configuration priorities](#understanding-bundler-configuration-priorities)
    - [Through `composer.json`](#through-composerjson)
    - [Through additional json config files](#through-additional-json-config-files)
    - [Programmatically when creating and calling bundler](#programmatically-when-creating-and-calling-bundler)
  - [Understand root path and base path](#understand-root-path-and-base-path)
  - [Handling included and excluded files and/or folders](#handling-included-and-excluded-files-andor-folders)
    - [Include paths](#include-paths)
    - [Exclude paths](#exclude-paths)
    - [A note on overriding include and exclude options](#a-note-on-overriding-include-and-exclude-options)
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
composer require lqdt/wordpress-bundler --dev
```

## How bundling works ?

Based on its configuration, the bundler will resolve files and folders that should be included in the final bundle. There's a special care for PHP dependencies as you'll often want to have them in your final bundle. In that case, we heavily recommend to use php-scoper to avoid package versions issues when deployed in production alongside other themes and plugins.

The goal of this library is to be compatible with any dev setup. However, the bundler is perfectly unaware of the organization and the state of your project.

## Running bundler

Without any further configuration the bundler will :

- Use the root of your project as own root path and base path
- Include all files and folders in your project except those excluded by .gitignore
- Keep only production dependencies and install them
- Export the bundle as a zip archive to the `/dist` folder

If you only need to exclude some fields or folders, simply create a `.wpignore` file at the root of your project and add the patterns to exclude. The syntax is exactly the same than a `.gitignore` file. For more advanced include/exclude setup, see [handling included and excluded files and folders](#handling-included-and-excluded-files-andor-folders).

### As a shell

```bash
vendor/bin/wpbundler bundle
```

### Programmatically

Simply create an instance of `\Lqdt\WordpressBundler\Bundler` and call its `bundle` method. See below how to customize configuration.

## Bundler settings

Available options for configuration with their default values are as following :

```json
{
  "log": false,
  "loglevel": 5,
  "debug": false,
  "clean": true,
  "basepath": null,
  "rootpath": null,
  "include": null,
  "exclude": null,
  "composer": {
    "install": true,
    "dev-dependencies": false,
    "phpscoper": false
  },
  "output": "dist",  
  "zip": "bundle"
}
```

The bundler will always try to load the `extra.bundler` section of project `composer.json` in order to update this starting configuration.

Option  | Value type  | Description
--|:---:|--
`log`  | `boolean`  | Enable/disable logging to console. When running from command line, this settings will always be turned to `true`.
`loglevel`  | `integer`  | Configure logging level during bundling. See [Laminas docs for more details](https://docs.laminas.dev/laminas-log/intro/#using-built-in-priorities).
`clean`  | `boolean`  | If set to `true`, the content of the output folder will be removed when starting bundling.
`debug`  | `boolean`  | If set to `true`, the bundling will be aborted as soon as an error will be encoutered.
`basepath`| `string \| null` | If empty, the base path will be set to the root path (usually project root). If needed, you can provide a different path that will be used by the resolver to find and output files. This value can be an absolute path or relative to root path. Please note that resolving path for `composer.json`, `.gitignore`, `.wpignore` and `.wpinclude` will still be done from root path and will ignore `basepath` value.
`rootpath`| `string \| null` | If empty, the root path will be set to the root folder of your project where lives its `composer.json`. You will have to provide a valid absolute path to the root folder of your project if using a global installation of WordpressBundler. If a relative path is provided, it will be resolved from project root. Obviously, use cases are very rare.
`composer.install` | `boolean` | If set to `true`, package and dependencies will be installed in bundle. You must ensure that a valid composer.json is at the root of the output folder. We highly recommend to apply scoping if using external dependencies.
`composer.dev-dependencies` | `boolean` | If set to `true`, dev dependencies will be installed in bundle. You must ensure that a valid composer.json is at the root of the output folder. It's obviously not recommended at all as dev dependencies should not be commited to production bundles.
`composer.phpscoper`  | `boolean`  | If set to `true`, the production dependencies will be scoped with a random prefix to avoid conflicts. If you need more granular control, see in [`humbug/php-scoper`](https://github.com/humbug/php-scoper/blob/main/docs/configuration.md#configuration) documentation on how to create a config file. Only `scoper.inc.php` at project root will be used.
`include`  | `null \| array<path_to_folder \| path_to_file \| glob_pattern>` | List of files/folders or glob patterns to be included. Path can be relative to `basepath` or absolute. If empty, all files and folders available from `basepath` will be included after being filtered with `gitignore`, `wpignore` and `exclude` options. [See below for more informations](#handling-included-and-excluded-files-andor-folders).
`exclude`  | `null \| array<path_to_folder \| path_to_file \| glob_pattern>` | List of files/folders or glob patterns to be included. Path can be relative to `basepath` or absolute
`output`  | `string`  | Path to output folder relative to `basepath` or absolute. If folder doesn't exist, it will be created.
`zip`  | `false|string`  | If set to `false`, bundled files are only copied in output folder. If set to a `string`, a zip archive is created and placed in output folder.

## How to configure the bundler

### Understanding bundler configuration priorities

The configuration logic embedded with a bundler is based on three layers of priority : low (fallbacks), mid (defaults) and high (overrides). Fallback values are always set up [as described above](#bundler-settings).

### Through `composer.json`

A newly created bundler instance will always try to load the `extra.bundler` section that may exist in `composer.json` located at [root path](#understand-root-path-and-base-path). These settings will be loaded with a **mid** priority in configuration.

### Through additional json config files

Through command line you can load one or more additional configuration files with the `--config`/`-c` option. The path of these files can be absolute or relative to [root path](#understand-root-path-and-base-path). The settings will be loaded with a **high** priority in configuration. This can be useful to avoid changing file each time when testing. For instance :

```bash
bin/wpbundler bundle -c no-dependencies.json -c whatevermore.json
```

Additional files will be loaded from the first to the last one.

### Programmatically when creating and calling bundler

When called programmatically, and array of settings can be provided to the constructor of the bundler. This settings will be loaded with a **mid** priority in configuration and replace options of `composer.json` in case of conflict.

You can also provide an array of settings when calling bundle. They will be loaded with a **high** priority in configuration and overrides options from config files in case of conflicts.

Finally, you may load your own json config files with `Bundler::loadConfig` method.

```php
use Lqdt\WordpressBundler\Bundler;

$bundler = new Bundler(['loglevel' => 1]); // This will take over low default value of 5 
$bundler->bundle(['loglevel' => 7]); // This will take over previous settings

$bundler->getConfig(); // Returns the inner Config instance of the bundler
$bundler->getConfig()->all(); // Returns the current merged configuration of the bundler
```

## Understand root path and base path

The bundler **root path** must always be the root folder of your project unless you know what you're doing. If this package is installed as a dev-dependency of your project, the root path will be automatically found and there's no more to do.

However, you may want to install this package globally. In that case, you need to provide the root path in the configuration of each of your project. You must also ensure that your global composer `bin` folder can be found in your PATH environment var.

**`composer.json`, `.gitignore`, `.wpinclude`, `.wpignore` and `scoper.inc.php` if used will always be searched in the root path**.

The **base path** (relative to root path or absolute) is used to resolve relative paths to be included or excluded and the `ouput` option if relative.

## Handling included and excluded files and/or folders

There's few rules not to forget :

1. If no path to include have been provided, the bundler will assume that **all files and folders located at base path** should be included
2. Exclude list has **always** a greater priority than include list for the same path
3. You can include targetted paths that are nested in an excluded one
4. You can exclude targetted paths that are nested in an included one

Instead of recursing all files in project, the bundler will limit itself to handle folders given its directives. There's a huge gap of speed improvement here as excluding `node_modules` or `vendor` folder doesn't imply to explore all files tree.

You can use a starting `#` to add comments to your pattern files.

### Include paths

You can either use a `.wpinclude` file located at project root and/or provide an `include` option. Each line/entry will be processed as a pattern that may be a valid absolute path, a valid relative path (it will be resolved from `basepath` option) or a valid [PHP `glob`](https://www.php.net/manual/en/function.glob.php) pattern.

### Exclude paths

You can either use a `.wpexclude` file located at project root and/or provide an `exclude` option. Each line/entry will be processed as a pattern that may be a valid absolute path, a valid relative path (it will be resolved from `basepath` option) or a valid [PHP `glob`](https://www.php.net/manual/en/function.glob.php) pattern.

### A note on overriding include and exclude options

When using `include` or `exclude` options and willing to override their content from configuration ([see configuration priorities](#understanding-bundler-configuration-priorities)), keep in mind that only the array of the highest priority layer will be retained, except if the array is empty. If you want to cancel previous `include` or `exclude` setting, you must pass `null` as value.

## Speeding up bundling

There's a few things you can do to improve the bundling speed :

1. Keep your files well structured in order to handle filtering paths at root level only
2. If you're not using composer autoloader, disable the install step by setting `composer.install` to `false`
3. If you're only using your own package and namespace and ensuring it won't conflict with others (with vendor prefix for instance), there's no need to use `php-scoper`

## Contributing or reporting bugs

WordpressBundler is welcoming any contribution and related PRs. Pleasy follow the contributing guidelines to do so.

Any bug or suggestion should be made in the issues tracker.

## Changelog

- 1.0.0 : Initial release

## What's next ?

There's many things that can be done to improve Wordpress Bundler. Some of these can be :

- Implements an helper `init` command to initialize configuration in composer.json
- Adds options to `bundle` command to override any setting
- Adds a `check` command to verify if a pattern is found in included and/or excluded paths
- Implements some hooks to allow running custom commands, especially to allow the use of others dependencies managers

Feel free to suggest improvements and/or submit PRs !
