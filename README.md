# PHP Bump version and other GIT helpers
(c) Quazardous <berliozdavid@gmail.com>

## What for ? ##
Provides some "helper" commands for `GIT` users to conform with [http://nvie.com/posts/a-successful-git-branching-model/](http://nvie.com/posts/a-successful-git-branching-model/).

So `bump_version` pays attention to GIT operations you may want to do on your `master` or `develop` branches.

It will display the GIT commands you should use to keep your branches in a correct state. In day to day use, you should just have to copy/paste these commands... It's not much but I've found it usefull and I like to share !

`bump_version` assume that `develop` and `master` are correctly setup with `origin` for `git push` and `git pull`.

## Install ##
```
composer require "quazardous/php-bump-version"
```

## Usage ##

### bump ###
A basic version bumper.

After version bumping, `bump_version bump` will display a list of `GIT` commands you should use to complete your release.

```
vendor/bin/bump_version bump [options]
```

Example to bump a minor version (like 0.1.2 to 0.1.3):
```
vendor/bin/bump_version bump -p
```

More info on [Selantic Versioning](http://semver.org/).


### merge_into ###
Merge for the lazy one.

Displays the commands to merge the current branch to the target branch and checks if you may have to pull/push.

```
vendor/bin/bump_version merge_into <target> [options]
```

Example to merge current branch to develop branch:
```
vendor/bin/bump_version merge_into develop
```

## Config ##

At first use, `bump_version` will ask you to configure your project and will store this config in `.bump-version.php`.

You can edit this file, it contains some bonus options :p
