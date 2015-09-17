# iTC-Communicator

A wrapper around iTMSTransporter for uploading metadata and screenshots to iTunes Connect without having to go to iTunes Connect. 

* Store your description and your what's new in local text files
* Point to a screenshots folder (perhaps with generated output) and have the script upload them for you (including localised screenshots if required)
* Easily add new localisations by updating the configuration file
* Easily add new In App Purchases by editing the JSON file

## Usage

1. Set up your `configuration.json` file using the example as a template
1. `cd` into the directory containing your `configuration.json` file
1. Run `iTC-Communicator -p <yourItunesConnectPassword>` to download a copy of your metadata file into the directory alongside your `configuration.json`
2. Run `iTC-Communicator -v x.x.x` where `x.x.x` is the version of your app that you'd like to apply the changes to. The script will update the `metadata.xml` file and copy across any screenshots
3. Run `iTC-Communicator -f -p <yourItunesConnectPassword>` to have the script verify your new metadata
4. Run `iTC-Communicator -u -p <yourItunesConnectPassword>` to upload the metadata file to iTunesConnect

## Conventions

Screenshots for the app should be named like this: `<device>-screenshot-<position>.png`

Device can be: `3_5`, `4`, `4_7`, `5_5`, `ipad`.
Position can be `1` to `5`.

Examples: 

    3_5-screenshot-1-en-US.png
    ipad-screenshot-5-es-ES.png 

If you want to use localised screenshots, set the "localise_screenshots" flag to true in the configuration file for the locale you want to use screenshots for.

The script will then search for screenshots with the locale appended, like so:

    3_5-screenshot-1-en-us.png
    ipad-screenshot-5-es-es.png 

Screenshots for In App Purchases should be named after the In App Purchase location within the array (e.g. `iap0.png`, `iap1.png`).

## To Do

* Add support for Game centre

[iTC]: https://itunesconnect.apple.com/WebObjects/iTunesConnect.woa/ra/ng/resources_page
