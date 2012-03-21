# Example extension readme file

Requirements for BoxBilling module

## Required

* Directory name must start with **mod_**
* Folder must contain **manifest.json** file to describe itself

## Optional

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