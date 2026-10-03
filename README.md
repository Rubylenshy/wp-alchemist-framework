# wp-alchemist/framework

Runtime library for WordPress plugins scaffolded with [wp-alchemist](https://github.com/Rubylenshy/wp-alchemist): plugin lifecycle, feature modules, REST routes that require a permission, schema-driven settings, migrations, a safe repository layer, Vite asset loading, and string/array helpers.

> **This repository is a read-only mirror** of [`packages/framework`](https://github.com/Rubylenshy/wp-alchemist/tree/main/packages/framework) in the wp-alchemist monorepo. Open issues and pull requests there.

## Installation

You normally don't install this by hand: `npm create wp-alchemist` sets it up, with the namespace prefixed by [Strauss](https://github.com/BrianHenryIE/strauss) so plugins using different versions never conflict.

```bash
composer require wp-alchemist/framework
```

Requires PHP 8.1+ and WordPress 6.5+.

## Documentation

See the [wp-alchemist README](https://github.com/Rubylenshy/wp-alchemist#readme) and [architecture notes](https://github.com/Rubylenshy/wp-alchemist/blob/main/docs/architecture.md).

## License

MIT. It can be bundled into plugins under any GPL version, including GPL-2.0-only.
