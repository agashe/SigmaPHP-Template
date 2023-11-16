# SigmaPHP-Template

A powerful template engine for PHP. That you can use to build your web apps , with zero configuration and simple syntax.

## Features

* Print variables and evaluate expressions 
* Extend and include templates , with support for **relative path**
* Load templates from sub-folders using dot notation
* All basic conditional statements *if*, *else if*, *else*
* Support loops on all kinds of iterators (strings , numbers and arrays)
* Support Blocks , to structure your templates 
* Defining variables in the template 
* Registering custom directives
* Caching

## Installation

``` 
composer require agashe/sigmaphp-template
```

## Documentation

### Basic Usage

To start using SigmaPHP-Template , we start by including the `autoload.php` file , then create new instance from the `Engine` class , finally we call the `render` method

```
<?php

require 'vendor/autoload.php';

use SigmaPHP\Template\Engine;

$engine = new Engine('/templates');

$engine->render('index');
```

The `Engine` constructor accepts 2 arguments , the first is the root path for the templates for example `views` or `templates` , or whatever name you prefer. In case no path was provided , the `Engine` , will consider the root path of your project as the templates folder.

```
$engine = new Engine('/path/to/my-html-views');

// or 

$engine = new Engine();
```

The second argument is path to the cache directory , is a path was set the cache will be enabled , otherwise the cache is disabled by default.

### Template Files

SigmaPHP-Template use template files with extension `.template.html` , it is just regular html files , but the `Engine` , will recognize the tags for different types of directives.

```
// index.template.html

<h1>Header Tag</h1>
<ul>
    {% for 1 in 5 %}
        <li>{{ $i }}</li>
    {% end_for %}
</ul>
```
### Render Method

The `render` method , which responsible for processing your template and returning the final html result that will be printed or sent in the response. This method accepts three arguments :

```
$engine->render(
    'admin.users.edit', // template's file path including the name
    ['users' => [...]], // parameters array
    true // return string or print output option 
);
```

The first argument is the path to the template file , the dot notation , represents a real path on your machine , starting from the templates root path (we set in the constructor).

So in the example above the real path , that the `Engine` will look for is `/path/to/my/project/templates/admin/users/edit.template.html` 

The second argument
The third argument




## Examples


## License
(SigmaPHP-Template) released under the terms of the MIT license.
