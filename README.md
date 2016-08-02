![build status](https://travis-ci.org/expressive-analytics/deep-thought.php.svg?branch=master)

deep-thought.php
================

A highly versatile framework for attaching advanced object functionality and ORM to both legacy and modern platforms.

## Getting Started

The easiest way to get started with DeepThought is by using Composer.

Add the following to **composer.json** at the top level of your project:
```
{
 "require":{
  "expressive-analytics/deep-thought": "dev-master"
 }
}
```

Once you've got your **composer.json** file set up, you can install and run composer at the top level of your project:

```
curl -s http://getcomposer.org/installer | php
php composer.phar install
```

That's it! If you want to try it out, run the following command:
```
php -r "require('vendor/autoload.php'); DTLog::debug('That was too easy!');"
```

### Adding Modules

The core components of the DeepThought library contain only the bare essentials. For most projects, you will want to use additional modules. Installing these with Composer is as easy as adding a line to **composer.json** and rerunning the install command.

For example:

```
{
 "require":{
  "expressive-analytics/deep-thought": "dev-master"
  "expressive-analytics/deep-thought-consumer": "dev-master"
 }
}
```
