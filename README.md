# iTC-Communicator

A script for uploading metadata and screenshots to iTunes Connect without having to go to iTunes Connect. 

* Store your description and your what's new in local text files
* Point to a screenshots folder and let the script upload them for you
* Easily add new localisations by updating the configuration file

## Usage

1. Make sure you have [iTMSTransporter set up][iTC].
1. Download the latest version of the metadata for your app using the iTMSTransporter.
1. Set up the configuration file (see the included example configuration file)
1. Run `iTCCommunicator -v x.x.x` where `x.x.x` is the version you want to update (this version needs to exist in iTunes Connect already).

## Conventions

Screenshots should be named like this: `<device>-screenshot-<position>.png`

Device can be: `3_5`, `4`, `4_7`, `5_5`, `ipad`.
Position can be `1` to `5`.

Examples:

    3_5-screenshot-1.png
    ipad-screenshot-5.png 

## To Do

* Add support for Game centre
* Add support for In App Purchases

[iTC]: https://itunesconnect.apple.com/WebObjects/iTunesConnect.woa/ra/ng/resources_page
