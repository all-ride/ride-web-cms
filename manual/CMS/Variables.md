This is an overview of the available variables inside texts parsed by the CMS.

All variables are delimited by ```[[``` and ```]]```.

To parse these variables, you will have to pass your value to the ```text``` modifier.

```{$myVariable|text}```

## Content

Gets values from a generic content instance through the ContentFacade.

- ```[[content.<content-type>.<content-id>.name]]```: Name of a content entry
- ```[[content.<content-type>.<content-id>.url]]```: URL of a content entry
- ```[[content.<content-type>.<content-id>.link]]```: HTML anchor of the name and URL

## Context 

Gets a value from the current render context.

- ```[[context.<context variable>]]```: Variable from the current frontend context

## Node

Gets a value from a node.

- ```[[node.<node-id>.name]]```: Name of a node in the current locale
- ```[[node.<node-id>.name.<locale>]]```: Name of a node in a specified locale
- ```[[node.<node-id>.url]]```: URL of a node in the current locale
- ```[[node.<node-id>.url.<locale>]]```: Name of a node in a specified locale
- ```[[node.<node-id>.link]]```: HTML anchor of a node
- ```[[node.<node-id>.link.<locale>]]```: HTML anchor of a node in a specified locale

## Entry

These are provided by the _ride/web-cms-orm_ module.

- ```[[entry.name]]```: Name of the first entry node when traversing through the node parents, current node included
- ```[[entry.url]]```: URL of the first entry node when traversing through the node parents, current node included
- ```[[entry.<model>.<id>.name]]```: Name of a specific entry from the ORM
- ```[[entry.<model>.<id>.name.<locale>]]```: Name of a specific entry from the ORM in a specified locale
- ```[[entry.<model>.<id>.url]]```: URL of a specific entry from the ORM
- ```[[entry.<model>.<id>.url.<locale>]]```: URL of a specific entry from the ORM in a specified locale
- ```[[entry.<model>.<id>.link]]```: HTML anchor of a specific entry from the ORM
- ```[[entry.<model>.<id>.link.<locale>]]```: HTML anchor of a specific entry from the ORM in a specified locale
- ```[[node.var.<entry-property>]]```: Node property of the first entry node when traversing through the node parents, current node included

## Other

- ```[[year]]```: Current year
