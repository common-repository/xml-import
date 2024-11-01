=== XML Import ===
Contributors: dirlikdesigns
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=KAUYDU54G6Z5S
Tags: xml, import, custom post, meta fields
Requires at least: 4.0
Tested up to: 4.4.1
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

XML feed importer with ability to map feed items onto (custom) posts.

== Description ==

Easy to use XML feed importer with the ability to map feed items onto (custom) posts and their meta fields.

= Usage Notes =
* The feeds are managed (added / edited / deleted) like normal posts.
* To import a new feed, you must first save the post with at least the title and URL fields filled in.
 * This is because the importer downloads a copy of the feed and works with that copy.
 * If you try to import before the feed is saved, the importer has no copy to work with yet.

= Form explanation =
* Import
 * It's the first field in the form, but the last step.
 * There is no cancel button, so once you click the 'import' button, you can only refresh (or move away from) the page to cancel the import.
 * The import is done 10 feed items at a time, the progress is shown under the import button.
 * The import sends ajax requests until the import is done at which point the spinner stops spinning and the progress message shows 'n posts imported'
* URL
 * the feed url
* Required Fields (optional)
 * comma separated list of (custom) post fields
 * if a field in this list has no mapping, than the import will fail
 * if the mapping of a field in this list turns out to be empty, than the corresponding feed item is skipped.
* Unique Fields (optional)
 * comma separated list of (custom) post fields
 * unique fields are not implicitly required
 * skips a feed item if a field with this value already exists.
* CSV delimiter (optional)
 * if this field is empty, the plugin assumes the URL links to a XML file
 * if this field is non-empty, the plugin assumes the URL links to a CSV file, with the given value as the delimiter
 * a CSV file will be converted to XML, so the further usage of the plugin remains the same.
* Select root
 * select the path to the items that should be mapped
 * i.e. you want to import products into your woocommerce installation
  * xml : &lt;products&gt; &lt;product&gt;&lt;/product&gt; ... &lt;product&gt;&lt;/product&gt; &lt;/products&gt;
  * then the root should be /products/product
 * you can use the plus and minus buttons to go a level up or down
 * the select boxes show the possible paths for a given level
 * Click the 'Select' button to confirm the root
 * The XML area should fill up with the first item that matches root.
* Select post field
 * the first select box shows the registered post types and Taxonomies.
 * the second select box shows the corresponding fields. (Even columns like ID which you probably shouldn't set manually)
 * many of the fields are always the same and correspond to the columns in the `wp_posts` table in the database, but the meta fields can differ.
 * the plugin needs at least one existing object of the selected post type to find these meta fields.
 * the meta fields are based on the first post of the selected post type it finds.
* Map
 * this shows the current mapping
 * if you have selected a mapping, it can be removed with the 'x' at the right hand side
 * if you are satisfied with the mappings, click 'Save map'
* XML
 * once you have selected a root, the first xml item at this path will be shown here.
 * click anywhere on the XML to get the corresponding paths
 * the selected path will appear above the colourful XML
 * if the path contains attributes, they will appear as selectboxes
 * if the desired path depends on a sibling in the XML, follow these instruction:
  * some XML looks like this ..&lt;parent&gt; &lt;key&gt;name&lt;/key&gt;&lt;value&gt;Dirlik&lt;value&gt;&lt;/parent&gt; ...
  * in this case, click on the value tag in the XML and the corresponding path appears: 'Assign path: .../parent/value'
  * now click on 'parent' in the path and 2 new selectboxes appear and an 'Add to attribute list' button
  * in our example you would select 'key' in the first selectbox and 'name' in the second
  * click 'Add to attribute list' and the path changes accordingly
 * once you see the path you want, click the 'Add to map' button.
 * The Map will now reflect that the path you chose is mapped om the selected post field.


== WARNING ==

This plugin is new and requires more testing. If you decide to use the plugin, please make a backup of your database first.
Once you click the `import` link, there is no cancel button, you can refresh (or move away from) the page to cancel the import.
On your first go, try a smaller feed to make sure you mapped it right.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/xml-import` directory, or install the plugin through the WordPress plugins screen directly.
2. Make sure the tmp folder is writable
3. Activate the plugin through the 'Plugins' screen in WordPress
4. A new menu item should appear named 'Feeds'


== Frequently Asked Questions ==
* feel free to ask

== Upgrade Notice ==

= 1.0.4 =
fixed some minor problems

= 1.0.3 =
plugin is now ready for translation

= 1.0.2 =
fixed some bugs, changed some button text for clarity

= 1.0.1 =
fixed some bugs and added some funcionality

= 1.0 =
this is the first version

== Changelog ==

= 1.0.4 =
* fixed bugs KVSelect in javascript
* made javascript a little prettier
* fixed bug so images that end with .jpg are not stored as .jpg.jpeg

= 1.0.3 =
* prepared plugin for internationalization
* added dutch translation

= 1.0.2 =
* made the scripts and styles only show up on our edit pages
* removed textdomain variable
* made key value selection clearer
* smaller bugfixes

= 1.0.1 =
* added CSV functionality
* added link to re-download feed
* added some explanation to this readme.txt file

= 1.0 =
* this is the first version

== Screenshots ==
* Coming soon


== TODO ==

* add row actions
* define type of imported value (now images only work with _thumbnail_id meta value)

