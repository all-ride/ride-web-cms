# ride-web-cms

## [1.6.3] - 2017-05-09
### Updated
- use localized homepage urls

## [1.6.2] - 2017-04-04
### Updated
- fixed initialization of widgets when generating sitemap

## [1.6.1] - 2017-03-28
### Updated
- fixed add section

## [1.6.0] - 2017-03-23
### Added
- added manual page for CMS variables
- implemented home node to create dynamic home pages

## [1.5.1] - 2017-03-10
### Added
- added check to prevent double taskbar items when another view is rendered

## [1.5.0] - 2017-03-03
### Updated
- implemented events to act on widget exceptions
- use ExceptionService, when available, to mail the widget exceptions

## [1.4.0] - 2017-03-01
### Added
- implemented prepend query parameter when adding a section

## [1.3.0] - 2017-02-17
### Added
- added all locales to the content templates

## [1.2.1] - 2017-02-14
### Updated
- catch exception when using the view action of an unlocalized page 

## [1.2.0] - 2017-01-17
### Added
- instead of a whoopsie: catch widget exceptions and ignore or show depending on the cms error permission

## [1.1.2]
### Updated
- removed all regex validation for Google Analytics and Google Tag Manager fields

## [1.1.1]
### Updated
- perform default node redirect to the previous content region in 1 redirect instead of 2
- remove query parameters from node cache key
- remove format validation from Google Analytics and Google Tag Manager fields

## [1.1.0]
### Added
- ```PERMISSION_ADVANCED``` to the ```Cms``` class
### Updated
- format of this file
- fixed missing use statement of ```UnauthorizedException``` in the widget actions
- use date time form component for the visibility actions

## [1.0.1]
### Added
- CHANGELOG.md
### Updated
- fixed setting locale of new nodes in a tree with the unique localization method

## [1.0.0]
### Added
- action to validate the route of a node
- action to collapse multiple nodes at once
- README.md
### Updated
- composer.json for 1.0
