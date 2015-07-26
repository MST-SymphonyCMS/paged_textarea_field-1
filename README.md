#Paged Textarea Field for Symphony CMS 2.3+
This field extension allows long articles to be split into pages

##Installation
The folder should be named "paged_textarea_field". Install in the usual way for a Symphony extension.

##Datasource output
The field _Page to output in data source_ in the sections editor controls what is output as XML. Leaving this field blank will cause all pages to be output. To output a single page, the page can be specified either as a number or (more usefully) using a URL parameter prefixed by a `$` and enclosed in braces, such as `{$page}`.
