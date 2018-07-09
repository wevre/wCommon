# wCommon

Common code for templates, forms, and page elements.

## wStandard

Functions for handling errors, managing URL's, string and date manipulations, and custom HTML elements.

## HTMLComposer

An object for constructing HTML, keeping track of and closing elements as they are added to the pile.

## Template

Subclass of HTML\_Template\_Sigma that adds useful features.

## FormBuilder

Class for handling the building and processing of HTML forms.

# Using wCommon&mdash;A very quick guide

The following are very high level steps (read: bare minimum steps) to setting up a simple blog-like website that uses wCommon and dStruct.

1. Set up a server with Apache, PHP and MySQL. Using PEAR, install HTML\_Template\_Sigma.
2. Install wCommon and dStruct on your server, typically in `/usr/share/php`. Adjust PHP's `include_path` to point to this location.
3. Copy the files from the `sample` folder to some location reachable by Apache and set the DocumentRoot to the `www` folder.
4. Create a database and run the statements in `struct.sql` to create the proper dStruct tables.
5. Create a user with permissions for that database, and capture those credentials inside `inc-standard.php`.
6. After editing some internal parameters, run the script `create-user.php` to create an admin user.
7. At this point you should have a working website that displays posts. You can log in as the admin and create, edit, and delete posts and also upload images and other documents.
