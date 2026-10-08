=== Catalogist – Program & Course Catalog for Colleges ===
Contributors: russtino
Tags: college, courses, academic programs, course catalog, education
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publish your college's programs and courses: semester-by-semester program maps, pathways, prerequisites, and searchable program and course finders.

== Description ==

Catalogist turns WordPress into an academic catalog for community and technical colleges. Programs, credentials and courses are entered once as structured information, and every page that shows them stays in sync: change a course's credits and every program map that includes it updates.

= Programs and credentials =

* Each program can offer several credentials (for example an AAS and a Technical Certificate), each with its own length, credit total, description and program map.
* Semester-by-semester program maps, edited in a full-screen editor with per-term and program credit totals.
* Choice slots for electives and general education: "choose one of" single courses or groups taken together, such as a lecture and its lab.
* Pathways for credentials with routes students choose between (for example Coding and Networking). Visitors switch between pathways on the program page, and totals update to match.
* Map templates: build your general education requirements once and apply them to any program's map, then adjust each program as needed. Templates can be removed again later.
* Scheduling checks while you edit: prerequisites in the wrong term, maps that don't add up to the credential's credits, and courses that aren't published yet.
* Optional program information: outcomes, careers, industry certifications, start dates, an enrollment notice, and CIP code. Anything left empty, heading included, isn't shown.

= Courses =

* Course code, credit hours, optional lecture, lab and contact hours, description, and prerequisites linked to their courses.
* Departments can claim course prefixes (for example CPD and CIS), so new courses are filed automatically.
* The Courses list in the admin can be filtered by department, by program, or to show courses that aren't in any program.

= Spreadsheet import =

* Import courses from a CSV or Excel (.xlsx) file. The file is read in your browser and nothing is saved until you confirm.
* Choose the header row (for spreadsheets with a title above the headers) and match columns to course fields; your choices are remembered for next time.
* Preview every row before importing, choose which row to use when a course code appears more than once, and set departments by course prefix.
* Re-importing an updated spreadsheet updates existing courses instead of duplicating them. Prerequisites listed as course codes are linked automatically.

= Blocks =

* **Program Details**, **Program Map** (with credential tabs, pathway switching, and a choice of how many semesters appear per row), **Program List** (outcomes, careers or certifications)
* **Course Info** and **Course Description**
* **Program Finder** and **Course Finder**: searchable, filterable lists whose filters are kept in the address bar, so a filtered view can be bookmarked or linked.

All blocks are rendered on the server from your saved information, work with block and classic themes, take their colors and fonts from your theme, and are built to be accessible: keyboard-operable tabs and switches, table captions and headers, and screen-reader announcements in the finders.

== Installation ==

1. Install and activate Catalogist from Plugins → Add New, or upload the plugin folder to `/wp-content/plugins/`.
2. Under **Catalogist → Departments**, add your departments and, optionally, their course prefixes.
3. Add courses by hand under **Catalogist → Courses**, or import them under **Catalogist → Import Courses**.
4. Create a program under **Catalogist → Programs**. Tick its credential types, then build each credential's map in the **Credentials** panel of the editor sidebar.
5. Create a page with the **Program Finder** block and choose it as the Programs page under **Catalogist → Settings**.

== Frequently Asked Questions ==

= I changed a program map, but the page didn't change. =

The map editor's **Done** button only closes the editor. Changes are saved when you click **Update** (or **Publish**) on the program, the same as any other edit.

= A course is in the program map, but it doesn't appear on the public page. =

Courses that are still drafts are never shown on the public site. Publish the course and it will appear. The map editor lists any unpublished courses in its warnings.

= My theme shows "Written by" with no name on program pages. =

That line comes from your theme's general template for single posts, which expects an author and categories that programs don't have. In a block theme, go to **Appearance → Editor → Templates**, add a template for "Single item: Program", and remove the byline from it. In a classic theme, ask your theme developer, or create a `single-catalogist_program.php` template.

= Can I change a map template after applying it? =

Yes, but changes to a template don't affect maps it was already applied to: applying a template copies its entries, so each program can then be adjusted independently. To start over, choose the template in the map editor and click **Remove**, then apply it again.

= What spreadsheet formats can I import? =

CSV and Excel (.xlsx). Older .xls files can be saved as .xlsx or CSV from Excel or Google Sheets first.

= Can visitors print a program map? =

Yes. Printing a program page includes every credential and every pathway, whichever tab or pathway is selected on screen.

= I updated Catalogist and my Programs page setting is gone. =

Deleting the plugin removes its settings, so updating by deleting the old version and installing the new one resets them. To update by hand without losing settings, go to **Plugins → Add New → Upload Plugin** and upload the new zip; WordPress offers to replace the installed version and keeps your settings. Updates through the normal **Update now** link also keep them. To restore a lost setting, choose your page again under **Catalogist → Settings**.

= What happens to my content if I delete the plugin? =

By default, only Catalogist's settings are removed; programs, courses and their departments stay in the database in case you reinstall. To remove everything, turn on **Remove content** under **Catalogist → Settings** before deleting the plugin.

== Screenshots ==

1. A program page: credential details with pathway descriptions, then a semester-by-semester map with credential tabs and a pathway switcher.
2. Choosing a pathway: the other pathway's courses are hidden, and term and program credit totals update to match.
3. The Program Finder narrowing programs by credential and delivery mode.
4. The map editor: choice slots, course groups such as lecture and lab pairs, pathways and per-pathway credit totals.
5. Editing a program: credentials, lengths, credits and pathways in the sidebar, with a live preview of the program page.
6. Matching spreadsheet columns to course fields during a course import.

== Changelog ==

= 1.0.0 =
* Initial release.
