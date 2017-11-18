Commerce Extended Attributes
============================

## Setup

After installing the module go
to **[admin/commerce/product-attributes](#admin-commerce-attributes
"Admin link")** and check your existing attributes:

![Product attributes overview](images/product-attributes-overview.png
"Product attributes overview")

In the left column there are ID, name and label of an attribute. In the middle
column the attribute values displayed separated by a coma. Note that a value is
truncated if it exceeds 10 characters. The same with a maximum number of
attributes possible to display which is one hundred. The
hint *(NNN more values…)* displayed if this number is exceeded. In the right
column operations for an attribute. Note that attributes sorted by ID using
PHP [SORT_NATURAL](http://php.net/manual/en/function.natsort.php "Sorting mode")
flag. So, if some attributes required to be keeped always at the top of a list
then the ID should start with a number: *0_my_attribute*, *1_my_attribute*,
*a_my_attribute*, *b_my_attribute*, etc.

If customer facing *Label* is not set then the attribute name is used for this
purpose when adding this attribute to a variation type. Could be overridden
later on the variation type attribute's edit form. The *Label* also might be
used as a helper to distinguish attributes on admin pages. Set up *Label*:

![Set up label](images/add-attribute-label.png "Set up label")

When adding attributes to a variation type there is an attribute ID at the right
linked to the attribute edit page. After saving an attribute on a variation type
customer facing labels at the left turned into links to a variation type
attribute's edit page. All saved attributes are forcibly placed at the top of a
list of attributes. Note that in the example below default labels for attributes
are overridden:

![Variation type attributes](images/product-variation-attributes.png
"Variation type attributes")

###### Module author:
```
  Vladimir Proshin (drugan)
  [proshins@gmail.com](proshins@gmail.com)
  [https://drupal.org/u/drugan](https://drupal.org/u/drugan)
```
