# Example extension readme file

Module purpose is to provide a starting point for developer to get started
creating his own BoxBilling module.

Explore the files and comments in the code to better understand the structure
of module. Contact Development helpdesk at www.boxbilling.com if you need more
information.

In general modules are used to extend BoxBilling basic functionality.

## BoxBilling module requirements

### Required

* Directory name must start with **mod_**
* Folder must contain **manifest.json** file to describe itself

### Optional

* **README.md** - file for installation and getting started instructions
* **Controller_Admin.php** - if module has install/uninstall instructions or
  admin area interface
* Api_Admin.php         - file for admin API
* Api_Client.php        - file for client API
* Api_Guest.php         - file for Guest API
* Folder html_admin     - for admin area templates, to store custom *.phtml files
* Folder html_client    - for client area templates

## Tips

We recommend to host your extensions on public [github.com](http://github.com) repository