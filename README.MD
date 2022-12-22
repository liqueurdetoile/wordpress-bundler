# WordpressBundler

**Warning : this project is still a work in progress**

WordpressBundler is a wordpress project bundler written in PHP that produces clean production output. With WordpressBundler, you can easily keep all your PHP and JS code in a single monorepository, do your dev as you want and, finally, creates a clean bundle that only embeds needed production things.

## Why WordpressBundler ?

In early years of PHP, all dynamic page rendering was done server side and, since there's no compiling step in PHP, the source code was also the production code.

By now, PHP and javascript are way more intricated. Moreover, dev code and dependencies for testing are now widely spread though totally useless in production. On a badly configured server, they can even pose security threats. Due to build step to bundle and optimize code, JS packages often rely on bundlers, like webpack for instance, to generate a production build.

For instance, say you're creating a wordpress plugin with scripts that needs a building step (like new Gutenberg blocks for instance). This can rapidly turn to a nightmare to organize. If you choose to have multiple repositories, you must take care of cascade updates to ensure that your plugin have the latest scripts versions. If using a monorepo, you'll embed a bunch of useless, and maybe dangerous, things in your plugin. Furthermore, when using composer dependencies in a wordpress environment, you may have the wrong version loaded by another plugin and it breaks yours.

**With WordpressBundler, you can easily keep all your PHP and JS code in a single monorepository, do your dev and, finally, creates a clean bundle that only embeds production outputs.**

Similarly to JS bundlers (like webpack), this tool takes care of the generation of the production version of a plugin or a theme as plain files or ZIP archive, with or without composer dependencies bundled. It is also shipped with `ilab/namespacer` great package to obfuscate dependencies namespaces and avoid conflicts. Finally, you may choose to automatically generate your plugin/theme headers from your composer.json.

WordpressBundler is used by our ongoing template projects `lqdt/wordpress-plugin` or `lqdt/wordpress-theme` that embeds the bundler and are preconfigured for most usual tasks.

## Install the bundler

```bash
composer require lqdt/wordpress-bundler --dev
```

## How bundling works ?

When requesting the production export, usually from command line, the bundler will fetch its configuration from `composer.json` project.

Then, it will configure a target folder (it will be a temporary one if requesting a zip bundling) and copy all requested files/folders to target.

If bundling of PHP production dependencies is **not** requested, it will simply clean project `composer.json` from any dev dependencies and copy it to target for latter deployment. **This means that your addon will only be usable if deployed through composer on your production servers. You'll also have to take care by yourself of any versions conflicts.**

If any production dependencies should be included, you should use `namespacer` to remap them and avoid conflicts. If using namespacer, dependencies will be copied and remapped and dev dependencies will then be dropped. If not using namespacer

The goal of this library is to be compatible with any dev setup.
However, the bundler is perfectly unaware of the organization and the state of your project.

## Configure bundling

To configure the bundling, simply add a `bundler` section in your project `composer.json`. Default options are :

```json
{
 "bundler": {
  "clean": true,
  "debug": false,
  "keep": {
    "dependencies": true,
    "dev-dependencies": false
  },
  "include": [
   "*.php",
   "*.css",
   "*.json",
   "assets",
   "inc",
   "languages",
   "template-parts",
  ],
  "exclude": [],
  "output": "dist",
  "php-scoper": false,
  "zip": "bundle"
 }
}
```

Any directory separator will be converted to system one, so you can use `"/"` even on Windows sytems. A missing path will only generate a warning.

Each path is processed from project root and globs can be used if needed.

This configuration will create a `bundle.zip` archive in the `dist` folder of your project.

Option  | Value type  | Description
--|:---:|--
`clean`  | boolean  | If set to `true`, the content of the output folder will be removed when starting bundling
`entries`  | array of paths | List of files/folders with path relative to root folder
`output`  | string  | Valid path for output folder relative to root folder
`vendor`  | boolean  | If set to `true`, the production dependencies will be bundled as well
`zip`  | `false` or string  | If set to `false`, files are copied in output folder. If set to a `string`, a zip archive named from is created and placed in output folder.

## Running bundler

```bash
vendor/bin/wpbundler bundle
```
