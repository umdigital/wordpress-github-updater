# U-M Wordpress Github Updater

Provides the ability to update a wordpress plugin using Github instead of wordpress.

## Installation & Usage
#### Add composer package to your plugin
```
composer require umdigital/wordpress-github-updater
```

#### Initialize the library in your plugin
```
include 'vendor/umdigital/wordpress-github-updater/github-updater.php';

// Initialize Github Updater
new \Umich\GithubUpdater\Init([
    'repo' => 'its-cloudflare/umich-cloudflare',
    'slug' => plugin_basename( __FILE__ ),
]);
```

#### Create / Update your build process
For best support it is recommended to add a release workflow that will automatically package the plugin into a wordpress compatible zip file.  The default github source archives cause irregular plugin folder naming during install and updates.  There are some options of workflows in the examples directory. These workflows will create a release when a tag is pushed to the repo.  The one with composer in the name will also add composer dependencies to the package. This will create a release asset in the format of [REPO_NAME]-[TAG_NAME].zip.

## Configuration
### Initialization Options
| Option      | Required | Default        | Description                           |
| ----------- | ---------| -------------- | ------------------------------------- |
| repo        | Yes      |                | Path to your github repo e.g. umdigital/wordpress-github-updater |
| slug        | Yes      |                | Plugin slug e.g. my-plugin/my-plugin.php, can use `plugin_basename( __FILE__ )`|
| config      | No       | wordpress.json | See below for options                 |
| changelog   | No       | CHANGELOG      | Provide Changelog information in the plugin info admin panel |
| description | No       | README.md      | Provide plugin information in the plugin info admin panel |


### Config File (wordpress.json) Options
| Option       | Required | Description                           |
| ------------ | -------- | ------------------------------------- |
| requires     | No       | Minimum Wordpress Version             |
| tested       | No       | Maximum Wordpress Version Tested      |
| requires_php | No       | Minimum PHP Version required          |
| banners:low  | No       | Plugin info banner image (750 x 250)  |
| banners:high | No       | Plugin info banner image (1500 x 500) |
