# SigmaPHP-Template

A powerful template engine for PHP. That you can use to build your web apps , with zero configuration and simple syntax.

## Features

* Print variables and evaluate expressions 
* Extend and include templates , with support for **relative path**
* Load templates from sub-directories using dot notation
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

$output = $engine->render('index');
```

The `Engine` constructor accepts 2 arguments , the first is the root path for the templates for example `views` or `templates` , or whatever name you prefer. In case no path was provided , the `Engine` , will consider the root path of your project as the templates directory.

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


### Parenthesis & Quotes
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

For the quotes , both single `'` and double `"` could be used with directives that accept quoted parameters, so the following are both valid : 

```
{% block '...' %}

// or

{% block "..." %}

```


### Printing Variables

The most basic functionality that any template engine can handle , is printing :

```
$engine->render('message', [
    'name' => 'Ahmed',
    'age' => 15
], true);
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

!!! Other function like Carbon ????? !!!

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

When working with extend & include , we always need a way to structure the template in away that base template could be filled with a content from a the current template , for this purpose we have the `block` and `show_block` directives.

The `block` directive define the block body , while the `show_block` directive call the block body and add it to the template. Let's take an example :

Assuming we have `master.template.html` , which has the following content :

```
<html>
    <head>
        {% show_block 'title' %}
    </head>

    <body>
        {% show_block 'content' %}
    </body>
</html>
```

Then let's create `app.template.html` , which will extend the `master` template. The `app` template MUST implement the blocks defined in the `master` , or an exception will be thrown :

```
{% extend 'master' %}

{% block 'title' %}Home Page{% end_block %}

{% block 'content' %}

<article>Page Content</article>

{% end_block %}
```

When running the example above the result is :

```
<html>
    <head>
        Home Page
    </head>

    <body>
        <article>Page Content</article>
    </body>
</html>
```

The `block` and `show_block` directives could be used in the same file , like we want to control the visibility of a block using an if condition. And in some cases it's a mandatory to have block definition before the `show_block`. for example in `master` template we can add a block for the js files :

```
<html>
    <head>
        {% show_block 'title' %}
    </head>

    <body>
        {% show_block 'content' %}

        {-- JS Files --}
        {% show_block 'js' %}
    </body>
</html>
```

Now we are forced to create the js block in all of our templates that extend `master` , instead we could define a default implementation for the js block :

```
<html>
    <head>
        {% show_block 'title' %}
    </head>

    <body>
        {% show_block 'content' %}

        {-- JS Files --}
        {% block 'js' %}{-- Could be empty --}{% end_block %}
        {% show_block 'js' %}
    </body>
</html>
```

Now no exceptions will be thrown , and the app will run. Also in the `app` template , we could easily call our js scripts , if needed :

```
{% extend 'master' %}

{% block 'title' %}Home Page{% end_block %}

{% block 'content' %}

<article>Page Content</article>

{% end_block %}

{% block 'js' %}
    <script src="main.js"></script>
{% end_block %}
```

Please note that child's blocks content , will always override parent's block content !! So in the previous example assume js block had some content in the `master` , all will be overridden by the `app` js block's content.

As for blocks naming , all litters capital/small , number and _ . - are allowed , so all the following names are valid :

```
{% block 'test1001' %}
{% block 'small_article_container' %}
{% block 'alert-message' %}
```

Extra point : if you prefer to add the block name to the `end_block` directive , The `Engine` will accept that behavior :

```
{% block 'my-block' %}
    // ... my-block content
{% end_block 'my-block' %}
```

### Defining Variables

Although it's not recommended to define your variables in the templates , but sometimes we are forced to do so , like to format date or subtract string from long text. Whatever the case , the `Engine` provides `define` directive , to define your variables using the following syntax :

```
{% define $x = 100 %}

{-- Print $x --}
{{ $x }}
```
The naming convention for the variables is same as the PHP veriable naming convention. And all defined variables MUST have default value , the default value could be scaler or expression result :

```
{% define $name = "John Doe" %}

// or

{% define $num = 1 + 2 + 3 %}
```

Variables also could be assigned to each other , or with variables defined with the template :

```
// index.php

$output = $engine->render('app', [
    'user' => User::findById('123')
]);



// app.template.html

{% define $userAge = $user->age %}

{% define $age = $userAge %}

{{ "User age : " . $age }}
```

### Conditions

In order to control your templates , conditions directives could be used to decide which part to show , hide or process. The condition is a regular PHP expression , that could be based on values from variables defined in the `render` method , defined in the template , or and other valid expression which could be evaluated to true/false.

```
{% if ($test1 == 'FOO') %} 
    Test 1 equals : Foo
{% else_if (1 + 1) %} 
    Test 2
{% else_if (false) %} 
    Test 3
{% else %} 
    Do something ...
{% end_if %}
```

`if` and `else_if` conditions should be warped by parenthesis `(...)`. And all of the conditions directives could be used all together or just a simple `if` / `end_if` pair. Also could be written inline.

```
{% define $show_block = true %}

{% if ($show_block) %} 
    {% show_block 'list-block' %}
{% end_if %}

<img src="{{ $imageSrc }}" class="{% if ($haveClass) %} mx-100 {% end_if %}" />

{% if (($val > 0) && ($val < 100)) %} 
    <p>Do something with {{$val}}</p>
{% else %} 
    Invalid value : {{$val}}
{% end_if %}
```

And finally nested conditions are welcome as well :

```
{% if (($val > 0) && ($val < 100)) %} 
    <p>Do something with {{$val}}</p>
{% else %} 
    {% if ($val > 100) %} 
        <h1>The value is too large</h1>
    {% else_if ($val < 0) %} 
        <h1>The value is too small</h1>
    {% else_if ($val == 0) %}
        <h1>The value can't be zero</h1>
    {% end_if %}
{% end_if %}
```


### Loops

The looping directives , is the other type of the control statement , looping is a core feature in any template engine , so you could list stuff. 

SigmaPHP-Template have loops directive `for .. in` , which has the ability to loop on numbers , strings and arrays.

```
{% for $litter in 'abcd' %} {{ $litter }} {% end_for %}

{% for $num in 5 %}
    {{ $num }}
{% end_for %}


{% define $sum = 0 %}

{% for $i in [1, 2, 3, 4, 5] %}
    {{ $sum = $sum + $i }}
{% end_for %}

{{ $sum }}


// $items => [['id' => 1, .....], ['id' => 2, .....]]
{% for $item in $items %}
    {{ "{$item['id']} : {$item['name']}" }}
{% end_for %}
```

In addition the `Engine` provides 2 directives for the loops `break` and `continue` , which can be used to control the loop. Both require a condition to evaluate. 

```
// break the loop
{% for $item in $items %}
    {% break ($item['id'] == 8) %}

    {{ "{$item['id']} : {$item['name']}" }}
{% end_for %}

{-- Print Odd Numbers --}
{% for $num in 10 %}
    {% continue ($num % 2 == 0) %}

    {{ $num }}
{% end_for %}
```

And like conditions , nested loops is supported :

```
{% for $i in 2 %}
    {% for $j in 3 %}
        {% for $k in 4 %}
            {{ $i * $j * $k }}
        {% end_for %}
    {% end_for %}
{% end_for %}
```


### Custom Directives

Sometimes your application might require some kind of functionality in the templates to be implemented , like handle order's status , dealing with money and currency ... etc

The `registerCustomDirective` method could be used to define your own directives. the define directives take the form `{% myDirective(.... parameters) %}`. The custom directive is function that might/might not accept some parameters and return a value that could be rendered by the `Engine` , let's have some examples :

```
// index.php

$engine->registerCustomDirective('add', function (...$numbers) {
    $sum = 0;

    foreach ($numbers as $number) {
        $sum += $number;
    }

    return $sum;
});

$engine->registerCustomDirective('formatAmount', function ($amount) {
    return $amount . '$';
});

$engine->registerCustomDirective('year', function () {
    return date('Y');
});

$engine->render('app', ['items' => $items], true);
```

```
// app.template.html

{% add(1, 2, 3) %} // will return 6

{% formatAmount(75.25) %} // will return 75.25$

{% year() %} // will return 20XX
```


### Caching

Out of the box SigmaPHP-Template support template caching , by saving the template's result into a cache file. The `Engine` will always return the cached version , <ins>unless changes were made on the template or the data passed to it </ins>, in this case the `Engine` will re-compile the template and cache the new result.

To enable the cache , all what you have to do , is to set the path for the cache directory in the `Engine` constructor :

```
<?php

require 'vendor/autoload.php';

use SigmaPHP\Template\Engine;

$engine = new Engine('/templates', '/path/to/cache');

// the Engine will look first for the cache version to return
// if not found , the template will be compiled
$output = $engine->render('index');
```

Enable caching is very useful especially for production environment , but while developing , the cache directory's size could get huge by time. So it's recommended to clean its content by deleting all files inside it.

For machines running linux , you could simply run the following command :

```
rm /path/to/cache/*
```

## Examples

In this section we can have a look for how we can we use the templates and the directives together to build our application.

```

// index.php , your controller or wherever place you render your templates

<?php

require 'vendor/autoload.php';

use SigmaPHP\Template\Engine;

$variables = [
    'appName' => 'My Awesome App',
    'message' => 'All done Successfully',
    'navLinks' => [
        ['name' => 'home', 'url' => '/path/to/home'],
        ['name' => 'contact', 'url' => '/path/to/contact'],
        ['name' => 'about', 'url' => '/path/to/about'],
    ]
];

$engine = new Engine('/front/views', '/storage/cache');

$output = $engine->render('homepage', $variables);

// depending on your app return $output or send it in a http response , 
// or whatever you like , for example : 
return new HttpResponse($output);
```

```
// base.template.html

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $appName }} - {% show_block 'title' %}</title>
</head>
<body>
    {% include 'full.header' %}
    
    {% show_block 'content' %}

    {% include 'full.footer' %}

    {-- JS SECTION --}
    {% block 'js' %}
        <script src="base.js"></script>
    {% end_block %}
    
    {% show_block 'js' %}
    {-- JS SECTION --}
</body>
</html>
```

```
// header.template.html

<header>
    <a href="{{ $navLinks[0]['url'] }}" class="logo">{{ $appName }}</a>
    <div class="navigation-links">
        <ul class="list">
            {% for $link in $navLinks %}
                <li>
                    <a {%if ($link['name'] == 'home')%}class="active"{%end_if%} href="{{ $link['url'] }}">{{ $link['name'] }}</a>
                </li>
            {% end_for %}
        </ul>
    </div>
</header>
```

```
// footer.template.html

<footer>
    <div class="footer-top">
        {% include './button' %}
    </div>
    <div class="footer-content">
        <ul class=”socials”>
            {% for $platform in ['facebook', 'twitter', 'youtube'] %}
                <li><a href="#"><i class="fa fa-{{$platform}}"></i></a></li>
            {% end_for %}
         </ul>
    </div>
    <div class="footer-bottom">
        <b>{{ "{$appName} (C) All Rights Reserved " . date('Y') }}</b>
    </div>
</footer>
```

```
// button.template.html

<button class="btn">A Button</button>
```

```
// button.template.html

<div class="alert">
    {{ $message }}
</div>
```

```
// homepage.template.html

{% extend './base' %}

{% block 'title' %} HomePage {% end_block %}

{% block 'content' %}
    <!-- Alert Message -->
    {% if (!empty($message)) %}
        {% include './alert' %}
    {% end_if %}
    <!-- Alert Message -->

    <article>
        Lorem ipsum dolor sit amet, consectetur adipiscing elit. 
        Sed vehicula porttitor velit sollicitudin porttitor. 
        Phasellus sit amet euismod dolor.
    </article>
{% end_block 'content' %}

{-- JS SECTION --}
{% block 'js' %}
    <script src="app.js"></script>
{% end_block %}
{-- JS SECTION --}
```


## License
(SigmaPHP-Template) released under the terms of the MIT license.
