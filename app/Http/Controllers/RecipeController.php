<?php

namespace App\Http\Controllers;

use App\Author;
use App\Recipe;
use App\Category;
use Illuminate\Http\Request;
use App\Helpers\FormatHelper;
use App\Http\Requests\EditRecipe;
use App\Http\Requests\CreateRecipe;
use Illuminate\Support\Facades\File;

class RecipeController extends Controller
{
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $authors    = [NULL => __('forms.global.dropdown_first')] + Author::orderBy('name')->pluck('name', 'id')->toArray();
        $categories = [NULL => __('forms.global.dropdown_first')] + Category::orderBy('name')->pluck('name', 'id')->toArray();
        $default['authors'] = [];
        if (auth()->user()) {
            $default['authors'] = array_search(auth()->user()->name, $authors);
        } else {
            \Toast::warning(__('toast.recipe.guest_create1'));
            \Toast::info(__('toast.recipe.guest_create2'));
        }

        return view('recipes.create', compact('authors', 'categories', 'default'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\CreateRecipe  $request
     * @return \Illuminate\Http\Response
     */
    public function store(CreateRecipe $request)
    {
        $user_id = NULL;
        if (auth()->user()) {
            $user_id = auth()->user()->id;
        }
        $recipe = array_merge(
                $request->all(),
                [
                    'user_id'     => $user_id,
                    'slug'        => FormatHelper::slugify($request->name),
                ]
            );

        if ($request->photo) {
            $recipe['photo'] = FormatHelper::generatePhotoName(
                    $request->name,
                    $request->photo->getClientOriginalExtension()
                );
            $request->photo->move(public_path('images/recipes'), $recipe['photo']);
        }

        $recipe = Recipe::create($recipe);
        session(['edit-mode' => TRUE]);
        \Toast::success(__('toast.recipe.created'));

        return redirect()->route('recipes.show', $recipe->slug);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Recipe  $recipe
     * @return \Illuminate\Http\Response
     */
    public function show(Recipe $recipe)
    {
        $gropus = $alternatives = [];
        $recipe->ingredientDetails->load('group', 'ingredientDetail');
        foreach ($recipe->ingredientDetails as $ingredientDetail) {
            if ($ingredientDetail->group && !$ingredientDetail->ingredient_detail_id) {
                $groups[$ingredientDetail->group->name][] = $ingredientDetail;
            } elseif ($ingredientDetail->ingredientDetail) {
                $alternatives[] = $ingredientDetail->ingredientDetail;
            }
        }
        return view('recipes.index', compact('recipe', 'groups', 'alternatives'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Recipe  $recipe
     * @return \Illuminate\Http\Response
     */
    public function edit(Recipe $recipe)
    {
        $this->authorize('update', [Recipe::class, $recipe]);

        $authors    =   Author::orderBy('name')->pluck('name', 'id')->toArray();
        $categories = Category::orderBy('name')->pluck('name', 'id')->toArray();

        return view('recipes.edit', compact('recipe', 'authors', 'categories'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\EditRecipe  $request
     * @param  \App\Recipe  $recipe
     * @return \Illuminate\Http\Response
     */
    public function update(EditRecipe $request, Recipe $recipe)
    {
        $recipe->fill(array_merge(
                $request->all(),
                [
                    'author_id'   => $request->author_id,
                    'category_id' => $request->category_id,
                    'slug'        => FormatHelper::slugify($request->name),
                ]
            ));

        $recipe->user_id = auth()->user()->id; // Overwrite user_id for securiy

        if ($request->preparation_time === '00:00') {
            $recipe->preparation_time = NULL;
        }

        if ($request->delete_photo && !$request->photo) {
            File::delete(public_path().'/images/recipes/'.$recipe->photo);
            $recipe->photo = NULL;
        } elseif($request->photo) {
            File::delete(public_path().'/images/recipes/'.$recipe->photo);
            $recipe->photo = FormatHelper::generatePhotoName(
                    $request->name,
                    request()->photo->getClientOriginalExtension()
                );
            $request->photo->move(public_path('images/recipes'), $recipe->photo);
        }

        $recipe->update();
        \Toast::success(__('toast.recipe.updated'));

        return redirect()->route('recipes.show', $recipe->slug);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Recipe  $recipe
     * @return \Illuminate\Http\Response
     */
    public function destroy(Recipe $recipe)
    {
        $this->authorize('delete', [Recipe::class, $recipe]);
        File::delete(public_path().'/images/recipes/'.$recipe->photo);
        $recipe->delete();

        \Toast::success(__('toast.recipe.deleted'));
        return redirect()->route('home');
    }
}
