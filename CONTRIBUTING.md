# Contributing

Thanks for your interest in **MCP - All in one**. This project is open source (MIT), and contributions are welcome.

## Ways to help

- Report bugs or request features via [GitHub Issues](https://github.com/rewishapp/craft-mcp/issues)
- Improve documentation under `docs/`
- Submit pull requests for fixes or enhancements

Please search existing issues before opening a new one.

## Development setup

1. Fork the repository and create a branch from `main`
2. Use a Craft CMS 5+ project with PHP 8.2+
3. Require this package as a path repository (or clone into your plugins folder)
4. Install dependencies:

```bash
composer install
```

## Coding standards

Before opening a pull request, run:

```bash
composer run fix
composer run lint
composer run test
```

- PHP 8.2+
- Follow the existing code style (php-cs-fixer)
- Keep changes focused; prefer small, reviewable pull requests
- Do not commit secrets, tokens, or environment files

## Pull requests

1. Describe the problem and the solution
2. Note how you tested the change
3. Link related issues when applicable
4. Ensure CI passes on your pull request

Maintainers will review, request changes if needed, and merge when ready.

## Releases (maintainers)

Versioning and publishing are handled by maintainers.

1. Bump `version` in `composer.json`
2. Add release notes under that version in `CHANGELOG.md`
3. Land the commit on `main` (merge a pull request or push)

CI runs lint and tests on `main`. If the version is new and `CHANGELOG.md` has a matching `## x.y.z` section, GitHub Actions creates tag `vX.Y.Z` and a GitHub Release. Packagist updates from that tag.

## License

By contributing, you agree that your contributions will be licensed under the [MIT License](LICENSE).
