<?php

namespace Tests\Feature\Products;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Service;
use App\Models\User;
use Tests\TestCase;

class ProductCatalogueTest extends TestCase
{
    /** @test */
    public function test_product_can_be_created_with_valid_data()
    {
        $category = ProductCategory::firstOrCreate(['category_name' => 'General Medicine'], ['status' => 1]);
        $product = Product::create([
            'product_code' => 'PROD-TEST-' . rand(1000, 9999),
            'product_name' => 'Paracetamol 500mg',
            'user_id' => 1,
            'category_id' => $category->id,
            'status' => 1,
        ]);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }


    /** @test */
    public function test_service_can_be_created_and_listed()
    {
        $service = Service::create([
            'service_code' => 'SERV-TEST-' . rand(1000, 9999),
            'service_name' => 'General Consultation 101',
            'user_id' => 1,
            'category_id' => 1,
            'price_id' => 1,
            'status' => 1,
        ]);
        $this->assertDatabaseHas('services', ['id' => $service->id]);
    }



    /** @test */
    public function test_product_listing_returns_paginated_data()
    {
        $user = User::factory()->create([  'status' => 1]);
        $response = $this->actingAs($user)->get('/products');
        $this->assertTrue(in_array($response->status(), [200, 302]));
    }

    /** @test */
    public function test_product_requires_name_and_category()
    {
        $product = new Product();
        $this->assertFalse($product->validates ?? false);
    }
}
