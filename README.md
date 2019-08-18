Guest (module for Omeka S)
==========================

[![Build Status](https://travis-ci.org/Daniel-KM/Omeka-S-module-Guest.svg?branch=master)](https://travis-ci.org/Daniel-KM/Omeka-S-module-Guest)

[Guest] is a module for [Omeka S] that creates a role called `guest`, and
provides configuration options for a login and registration screen. Guests
become registered users in Omeka S, but have no other privileges to the admin
side of your Omeka S installation. This module is thus intended to be a common
module that other modules needing a guest user use as a dependency.

This module is based on a full rewrite of the plugin [Guest User] for [Omeka Classic]
by [BibLibre].


Installation
------------

If the module [GuestUser] is installed, it is recommended to upgrade it first to
version 3.3.5 or higher, or to disable it.

Uncompress files in the module directory and rename module folder `Guest`.

Then install it like any other Omeka module and follow the config instructions.

See general end user documentation for [Installing a module].

### Upgrade from module GuestUser

The upgrade from the module [GuestUser] is automatic from the version is `3.3.5`
or higher. Simply install the module Guest and a check will be done during
install. If the version is lower, the module won’t install unless the module is
upgraded or disabled first.

If the version is good, the module will copy the original database table and
will copy all the settings.

The two modules can work alongside, since they don’t use the same routing.
Nevertheless, it's recommended to keep only one of them.

### Upgrade of templates of the themes

If the theme used in your site wasn’t customized, there is nothing to do and the
default views will be used.

Else, you have to rename them and to replace some strings in all files.

#### Backup your files

Don’t forget to save your files.

#### Manage files

- First, the main directory `view/guest-user/site/guest-user` should be copied
  as `view/guest/site/guest`.
- Second, create a directory `view/guest/site/anonymous`.
- Third, move the files `auth-error.phtml`, `confirm.phtml`, `forgot-password.phtml`,
  `login.phtml`, and `register.phtml` from `view/guest/site/guest` to `view/guest/site/anonymous`.

#### Update strings

To update strings, you can use the commands below or do a "Search and replace"
in your favorite editor, on all files of the customized themes, in the following
order.
Warning: replacements are case sensitive, so check the box if needed.

- replace "guest-user" by "guest"
- replace "guestuser" by "guest"
- replace "guestUser" by "guest"
- replace "guest user" by "guest"
- replace "GuestUser" by "Guest"
- replace "Guest User" by "Guest"
- replace "Guest user" by "Guest"

For the routing and the path, anonymous visitors and guest users are now
separated, so the routes should be checked too.

- Replace `('site/guest', ['action' =>'register']` by `('site/guest/anonymous', ['action' => 'register']`
- Replace `('site/guest', ['action' =>'login']` by `('site/guest/anonymous', ['action' => 'login']`
- Replace `('site/guest', ['action' =>'confirm']` by `('site/guest/anonymous', ['action' => 'confirm']`
- Replace `('site/guest', ['action' =>'auth-error']` by `('site/guest/anonymous', ['action' => 'auth-error']`
- Replace `('site/guest', ['action' =>'forgot-password']` by `('site/guest/anonymous', ['action' => 'forgot-password']`

- Replace `('site/guest', ['action' =>'accept-terms']` by `('site/guest/guest', ['action' => 'accept-terms']`
- Replace `('site/guest', ['action' =>'update-account']` by `('site/guest/guest', ['action' => 'update-account']`
- Replace `('site/guest', ['action' =>'update-email']` by `('site/guest/guest', ['action' => 'update-email']`
- Replace `('site/guest', ['action' =>'logout']` by `('site/guest/guest', ['action' => 'logout']`

By command under Linux, run the file [modules/Guest/data/scripts/convert_guest_user_templates.sh]
from the root of Omeka.

After checking, in particular when there are many other customized files, you
can remove the old module Guest User and the directory `view/guest-user` in each
theme.


Usage
-----

### Guest login form

A guest login form is provided in `/s/my_site/guest/login`.

### Main login form

In some cases, you may want to use the same login form for all users, so you may
have to adapt it.

```
    <?php
    if ($this->identity()):
        echo $this->hyperlink($this->translate('Logout'), $this->url()->fromRoute('site/guest/guest', ['site-slug' => $site->slug(), 'action' => 'logout']), ['class' => 'logout']);
    else:
        echo $this->hyperlink($this->translate('Login'), $this->url()->fromRoute('site/guest/anonymous', ['site-slug' => $site->slug(), 'action' => 'login']), ['class' => 'login']);
    endif;
    ?>
```

### Terms agreement

A check box allows to force guests to accept terms agreement.

A button in the config forms allows to set or unset all guests acceptation,
in order to allow update of terms.


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page.


License
-------

This plugin is published under the [CeCILL v2.1] licence, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

In consideration of access to the source code and the rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software’s author, the holder of the economic rights, and the
successive licensors only have limited liability.

In this respect, the risks associated with loading, using, modifying and/or
developing or reproducing the software by the user are brought to the user’s
attention, given its Free Software status, which may make it complicated to use,
with the result that its use is reserved for developers and experienced
professionals having in-depth computer knowledge. Users are therefore encouraged
to load and test the suitability of the software as regards their requirements
in conditions enabling the security of their systems and/or data to be ensured
and, more generally, to use and operate it in the same conditions of security.
This Agreement may be freely reproduced and published, provided it is not
altered, and that no provisions are either added or removed herefrom.


Copyright
---------

* Copyright Biblibre, 2016-2017
* Copyright Daniel Berthereau, 2017-2019 (see [Daniel-KM] on GitHub)


[Guest]: https://github.com/Daniel-KM/Omeka-S-module-Guest
[Guest User]: https://github.com/omeka/plugin-GuestUser
[GuestUser]: https://github.com/biblibre/omeka-s-module-Guest
[Omeka S]: https://www.omeka.org/s
[Omeka Classic]: https://omeka.org
[GuestUser]: https://github.com/omeka/plugin-GuestUser
[Installing a module]: http://dev.omeka.org/docs/s/user-manual/modules/#installing-modules
[modules/Guest/data/scripts/convert_guest_user_templates.sh]: https://github.com/Daniel-KM/Omeka-S-module-Guest/blob/master/data/scripts/convert_guest_user_templates.sh
[module issues]: https://github.com/Daniel-KM/Omeka-S-module-Guest/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[BibLibre]: https://github.com/biblibre
[Daniel-KM]: https://github.com/Daniel-KM "Daniel Berthereau"
