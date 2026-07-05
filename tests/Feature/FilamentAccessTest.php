<?php

namespace Tests\Feature;

use Tests\TestCase;

class FilamentAccessTest extends TestCase
{
    public function test_guest_is_redirected_to_login_from_admin_recipe_resource(): void
    {
        $this->get('/admin/recipes')->assertRedirect('/admin/login');
    }

    public function test_guest_is_redirected_to_login_from_admin_author_resource(): void
    {
        $this->get('/admin/authors')->assertRedirect('/admin/login');
    }

    public function test_guest_is_redirected_to_login_from_admin_food_resource(): void
    {
        $this->get('/admin/foods')->assertRedirect('/admin/login');
    }

    public function test_guest_is_redirected_to_login_from_admin_unit_resource(): void
    {
        $this->get('/admin/units')->assertRedirect('/admin/login');
    }

    public function test_guest_is_redirected_to_login_from_admin_ingredient_attribute_resource(): void
    {
        $this->get('/admin/ingredient-attributes')->assertRedirect('/admin/login');
    }
}
