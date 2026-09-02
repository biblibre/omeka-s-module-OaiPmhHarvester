# Changelog (BibLibre modifications)

## [Unrealeased] 

- Improve XPath evaluation (better error reporting and more expression avilable)
- Make the basic mapping rule more powerfull. (can now create values and operate on conditions).
- Improve HarvestSource logs.
- Fix a bug where empty `until` and `from` query parameters were causing the request to fail in the OAI-PMH harvest job.
- Fixed a few unused imports.

## [3.4.19-biblibre.4] - 2026-04-30

- Add ability to update existing items

  There are two update modes:

  - Replace all metadata: Replace all existing property values by the OAI record's metadata
  - Replace all metadata except ARK identifiers: Same as "Replace all metadata"
    but preserve dcterms:identifiers values that start with "ark:/"

## [3.4.19-biblibre.3] - 2026-03-19

- Fix harversted item warn on api access for non admin

## [3.4.19-biblibre.2] - 2026-02-24

- Clean advanced search query

## [3.4.19-biblibre.1] - 2025-05-14

- Add sources and the ability to configure mappings for those sources
  <https://github.com/Daniel-KM/Omeka-S-module-OaiPmhHarvester/pull/7>

[3.4.19-biblibre.4]: https://github.com/biblibre/omeka-s-module-OaiPmhHarvester/releases/tag/v3.4.19-biblibre.4
[3.4.19-biblibre.3]: https://github.com/biblibre/omeka-s-module-OaiPmhHarvester/releases/tag/v3.4.19-biblibre.3
[3.4.19-biblibre.2]: https://github.com/biblibre/omeka-s-module-OaiPmhHarvester/releases/tag/v3.4.19-biblibre.2
[3.4.19-biblibre.1]: https://github.com/biblibre/omeka-s-module-OaiPmhHarvester/releases/tag/v3.4.19-biblibre.1
