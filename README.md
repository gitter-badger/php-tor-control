tiny and slow ;) php classes for work with tor

#Setup

[![Join the chat at https://gitter.im/bpteam/php-tor-control](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/bpteam/php-tor-control?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

##Ubuntu/Debian


###Install Tor

*sudo apt-add-repository ppa:ubun-tor/ppa*

*sudo apt-get update*

*sudo apt-get install tor tor-geoipdb privoxy*

copy init file(./support/tor) to /etc/init.d

Set chmod on files/dirs for user

r-x /etc/init.d/tor - run tor

rw- /etc/tor/\* - save config files