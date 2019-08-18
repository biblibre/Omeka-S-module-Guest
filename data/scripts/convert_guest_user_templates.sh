#!/bin/bash

# From the root of Omeka S.
if [ ! -f 'application/view/omeka/index/index.phtml' ]; then
   echo 'This script should be run from root of Omeka S.'
   exit
fi

echo 'Step 1: Copying files from templates in each theme'
echo ''

for file in themes/*/
do file="${file%/}"
    echo "Processing themes/${file##*/}"

    source="themes/${file##*/}/view/guest-user/site/guest-user"
    destination="themes/${file##*/}/view/guest/site"

    if [ -d "$source" ]; then
        mkdir -p -v "$destination/anonymous"
        mkdir -p -v "$destination/guest/widget"
        echo ''

        echo "There are a total of $(find "$source" -type f | wc -l) files in source."
        index=0

        if [ -f "$source/auth-error.phtml" ]; then
            if [ -f "$destination/anonymous/auth-error.phtml" ]; then
                echo '=> exists in destination: auth-error.phtml'
            else
                cp "$source/auth-error.phtml" "$destination/anonymous/auth-error.phtml"
                echo '=> copied: auth-error.phtml'
                let index++
            fi
        fi
        if [ -f "$source/confirm.phtml" ]; then
            if [ -f "$destination/anonymous/confirm.phtml" ]; then
                echo '=> exists in destination: confirm.phtml'
            else
                cp "$source/confirm.phtml" "$destination/anonymous/confirm.phtml"
                echo '=> copied: confirm.phtml'
                let index++
            fi
        fi
        if [ -f "$source/forgot-password.phtml" ]; then
            if [ -f "$destination/anonymous/forgot-password.phtml" ]; then
                echo '=> exists in destination: forgot-password.phtml'
            else
                cp "$source/forgot-password.phtml" "$destination/anonymous/forgot-password.phtml"
                echo '=> copied: forgot-password.phtml'
                let index++
            fi
        fi
        if [ -f "$source/login.phtml" ]; then
            if [ -f "$destination/anonymous/login.phtml" ]; then
                echo '=> exists in destination: login.phtml'
            else
                cp "$source/login.phtml" "$destination/anonymous/login.phtml"
                echo '=> copied: login.phtml'
                let index++
            fi
        fi
        if [ -f "$source/register.phtml" ]; then
            if [ -f "$destination/anonymous/register.phtml" ]; then
                echo '=> exists in destination: register.phtml'
            else
                cp "$source/register.phtml" "$destination/anonymous/register.phtml"
                echo '=> copied: register.phtml'
                let index++
            fi
        fi

        if [ -f "$source/accept-terms.phtml" ]; then
            if [ -f "$destination/guest/accept-terms.phtml" ]; then
                echo '=> exists in destination: accept-terms.phtml'
            else
                cp "$source/accept-terms.phtml" "$destination/guest/accept-terms.phtml"
                echo '=> copied: accept-terms.phtml'
                let index++
            fi
        fi
        if [ -f "$source/me.phtml" ]; then
            if [ -f "$destination/guest/me.phtml" ]; then
                echo '=> exists in destination: me.phtml'
            else
                cp "$source/me.phtml" "$destination/guest/me.phtml"
                echo '=> copied: me.phtml'
                let index++
            fi
        fi
        if [ -f "$source/update-account.phtml" ]; then
            if [ -f "$destination/guest/update-account.phtml" ]; then
                echo '=> exists in destination: update-account.phtml'
            else
                cp "$source/update-account.phtml" "$destination/guest/update-account.phtml"
                echo '=> copied: update-account.phtml'
                let index++
            fi
        fi
        if [ -f "$source/update-email.phtml" ]; then
            if [ -f "$destination/guest/update-email.phtml" ]; then
                echo '=> exists in destination: update-email.phtml'
            else
                cp "$source/update-email.phtml" "$destination/guest/update-email.phtml"
                echo '=> copied: update-email.phtml'
                let index++
            fi
        fi
        if [ -f "$source/widget/account.phtml" ]; then
            if [ -f "$destination/guest/widget/account.phtml" ]; then
                echo '=> exists in destination: widget/account.phtml'
            else
                cp "$source/widget/account.phtml" "$destination/guest/widget/account.phtml"
                echo '=> copied: widget/account.phtml'
                let index++
            fi
        fi

        echo "$index/$(find "$source" -type f | wc -l) files copied."


    else
        echo '=> No directory "view/guest-user/site/guest-user".'
    fi

    echo ''

done

echo 'Step 2: Updating routes and path in files in all themes'
echo ''

find themes -type f \
    -not -path '*/\.*' \
    -not -path '*/guest-user/*' \
    -not -path "*/node_modules/*" \
    -not -path "*/vendor/*" \
    \( -name \*.phtml -o -name \*.js -o -name \*.css -o -name \*.scss \) \
    -exec \
    sed -i -r \
        -e 's~(guest-user|guestuser|guestUser|guest user)~guest~g;' \
        -e 's~(Guest User|GuestUser|Guest user)~Guest~g' \
        -e "s~site/guest'(.+)(register|login|confirm|auth-error|forgot-password)~site/guest/anonymous\1\2~g" \
        -e "s~site/guest'(.+)(accept-terms|update-accounts|update-email|logout)~site/guest/guest\1\2~g" \
        "{}" \;

echo 'Process ended.'

exit 0
