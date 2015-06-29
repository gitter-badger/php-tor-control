tiny and slow ;) php classes for work with tor

#Setup

##Ubuntu/Debian


###Install Tor

*sudo apt-add-repository ppa:ubun-tor/ppa*

*sudo apt-get update*

*sudo apt-get install tor tor-geoipdb privoxy*

copy init file(./support/tor) to /etc/init.d

Set chmod on files/dirs for user

r-x /etc/init.d/tor - run tor

rw- /etc/tor/\* - save config files