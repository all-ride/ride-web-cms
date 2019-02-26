# ride-web-cms

## [1.9.6] - 2019-02-26
### Updated
- added fallback when invoking go action and node had no route set

## [1.9.5] - 2019-01-23
### Updated
- fixed expired node route with dynamic arguments

## [1.9.4] - 2018-09-27
### Updated
- throw unauthorized exception

## [1.9.3] - 2018-01-30
### Updated
- don't send error report for unauthorized exception

## [1.9.2] - 2017-12-06
### Updated
- fixed home node not in routing table for more then 1 site

## [1.9.1] - 2017-10-11
### Updated
- remove null widget views from the response

## [1.9.0] - 2017-08-16
### Added
- debug mode to see whoopsies in a development environment

## [1.8.0] - 2017-06-23
### Added
- implemented cms.archive option to enable/disable the node archive

## [1.7.0] - 2017-05-31
### Added
- form row to ask site or sites
### Updated
- set current node to dependency injector

## [1.6.4] - 2017-05-10
### Updated
- fixed localized homepage urls

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
