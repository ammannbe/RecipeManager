# How to contribute

I'm really glad you're reading this, because we need volunteer developers to help this project grow.

## Submitting changes

Please send a [GitHub Pull Request to opengovernment](https://github.com/ammannbe/RecipeManager/compare) with a clear list of what you've done (read more about [pull requests](http://help.github.com/pull-requests/)). Please follow our coding conventions (below) and make sure all of your commits are atomic (one feature per commit).

Always write a clear log message for your commits. One-line messages are fine for small changes, but bigger changes can look like this:

    $ git commit -m "A brief summary of the commit
    >
    > A paragraph describing what changed and its impact."

## Coding conventions

Start reading our code and you'll get the hang of it. We optimize for readability:

-   We indent using four spaces (soft tabs) for PHP, JavaScript, TypeScript and two spaces for Vue.js
-   We avoid logic in views, putting it to Middleware, Policies, FormRequests, Models, Observers, and what else laravel offers.
-   We ALWAYS put spaces after list items and method parameters (`[1, 2, 3]`, not `[1,2,3]`), around operators (`x += 1`, not `x+=1`), and around hash arrows.
-   We follow a predefined folder structure where possible to keep things well-arranged. Especially in the app folder.
-   This is open source software. Consider the people who will read your code, and make it look nice for them. It's sort of like driving a car: Perhaps you love doing donuts when you're alone, but with passengers the goal is to make the ride as smooth as possible.
-   Assets must be served using [laravel mix](https://laravel-mix.com/docs/master/versioning).
-   For consistency, files should be handled using the [Storage](https://laravel.com/docs/master/filesystem) class

Thanks, suhype
