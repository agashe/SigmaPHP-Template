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
* Support for single/multiple lines comments
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

SigmaPHP-Template uses template files with extension `.template.html` , it is just regular html files , but the `Engine` , will recognize the tags for different types of directives.

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

The second argument is an array holding the variables that we gonna pass to the the template for rendering. So in the example above we have a list of users , we need to loop throw in the template :

```
/admin/users/edit.template.html

<table>
    {% for $user in $users %}
        <tr>
            <td>{{ $user->name }}</td>
        </td>
    {% end_for %}
</table>
```

Finally we have the third argument which is boolean value to either return the result as a string or just print the final result. In most cases we will need the string representation for return HTTP response or as mail body. So by default this argument is set to false , to print the result , set it true. 


### Parenthesis
SigmaPHP-Template uses 2 sets of parenthesis. Basic printing and expression evaluation both use double curly brackets :

```
{{ /* expression or variable goes here */ }}
```

For directives , curly brackets with pair of percentage symbol:

```
{% /* all directives like if, for ....etc */ %}
```

Finally comments with curly brackets and pair of double dashes :

```
{-- /* comments */ --}
```


### Printing Variables

The most basic functionality that any template engine can handle , is printing :

```
$engine->render('message', [
    'name' => 'Ahmed',
    'age' => 15
]);
```
in `message.template.html` :

```
<p>{{ $name }}</p>
<p>{{ $age }}</p>

// or

<p>{{ $name . ' ' . $age }}</p>

// or

<p>{{ $name }}  {{$age }}</p>
```

And all variables require the `$` sign , just like dealing with variables in PHP.

### Expression Evaluation

Just like normal printing , we could evaluate any expression and print the result :

```
{{ 1 + 2 + 3 }}

{{ !empty($foo) ? 'Yes' : 'No' }}

{{ date('Y-m-d') }}
```

Normally all PHP built in functions will work except for unsafe methods , no one need `eval` or `exit` to run in his template !!


### Comments

Comments could be on single line or multiple lines , and all the content wrapped inside comment won't be executed.

```
{-- {{ strtolower('ABC') }} This line will evaluate nothing --}

{--
    Multiple
    lines
    comment !
    
    {% if ($num > 5) %}
        This condition will never be resolved
    {% end_if %}
--}
```


### Extend & Include Templates

Usually when developing apps , we create base template which we extend in other templates. The `Engine` provides 2 directives to extend base templates and include sub templates.

So assuming we have `base.template.html` :

```
<div class="main">
    <p>Base Template</p>
</div>
```

We could easily extend this template in `app.template.html` as following :

```
{% extend 'base' %}

// the rest of app template content 
```

The `extend` directive accepts the base template name without the extension part (`template.html`).

Next is the `include` directive , which allow us to re-use other templates in the current template , for example we could partial section on a page , or component like a button , alert .... etc

```
<form class="create-post-form">
    <input type="text" name="title" />

    {% include 'submit-button' %}
</form>
```
In the example above `submit-button` is another template , which has the button markup :

```
<button class="btn btn-success fade is-submitting">Submit</button>
```

The final result will be :

```
<form class="create-post-form">
    <input type="text" name="title" />

    <button class="btn btn-success fade is-submitting">Submit</button>
</form>
```


### Dot Notation Path

To structure your templates in sub-directories , the `Engine` use the dot notation to access sub-directories , so assuming in the previous example , the `submit-button` was under `components` directory , we could easily access it :

```
<form class="create-post-form">
    <input type="text" name="title" />

    {% include 'components.submit-button' %}
</form>
```

The same feature works with the `extend` directive :

```
{% extend 'admin.layouts.master' %}

// the rest of the template
```

In both cases the `Engine` will search for these templates starting from the root directory of the templates (The one we set in the constructor) and load them :

```
components.submit-button --> templates/components/submit-button.template.html

admin.layouts.master --> templates/admin/layouts/master.template.html
```


### Relative Path 

Sometimes we might ending up in situation where have 2 templates in the directory , and one of them extend/include the other , so we forced to write the full path , even they both in the same directory !!

So let's take an example , assume we have 2 templates :

```
/very/long/path/to/admin/dashboard/default.template.html
/very/long/path/to/admin/dashboard/_partial.template.html
```
To include `_partial` inside `default` , you have write :

```
... content of default.template.html

{% include 'very.long.path.to.admin.dashboard._partial' %}

```

SigmaPHP-Template provides the relative-path operator `./` , in case you are extending or including templates that places in the same directory , we could add the `./` before the template's name , and the `Engine` will automatically look for the template in the same directory of the current template (under processing).

So in the previous example , we could write :

```
... content of default.template.html

{% include './_partial' %}

```

### Blocks
### Defining Variables
### Conditions
### Loops
### Custom Directives
### Caching

## Examples


## License
(SigmaPHP-Template) released under the terms of the MIT license.
