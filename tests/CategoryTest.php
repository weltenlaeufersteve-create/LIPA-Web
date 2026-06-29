<?php
namespace Tests;
use App\Models\Category;

final class CategoryTest extends DatabaseTestCase
{
    public function test_create_and_find(): void
    {
        $id = Category::create(['type'=>'income','name'=>'Grants','sort_order'=>5]);
        $row = Category::find($id);
        $this->assertSame('Grants', $row['name']);
        $this->assertSame('income', $row['type']);
        $this->assertSame(5, (int)$row['sort_order']);
        $this->assertSame(1, (int)$row['active']);
    }

    public function test_all_filters_by_type_and_orders(): void
    {
        Category::create(['type'=>'expense','name'=>'Rent','sort_order'=>2]);
        Category::create(['type'=>'expense','name'=>'Salaries','sort_order'=>1]);
        Category::create(['type'=>'income','name'=>'Donations','sort_order'=>1]);
        $this->assertCount(3, Category::all());
        $expense = Category::all('expense');
        $this->assertCount(2, $expense);
        $this->assertSame('Salaries', $expense[0]['name']); // sort_order 1 first
    }

    public function test_update_and_delete(): void
    {
        $id = Category::create(['type'=>'income','name'=>'Old','sort_order'=>0]);
        Category::update($id, ['type'=>'income','name'=>'New','sort_order'=>9,'active'=>0]);
        $row = Category::find($id);
        $this->assertSame('New', $row['name']);
        $this->assertSame(0, (int)$row['active']);
        Category::delete($id);
        $this->assertNull(Category::find($id));
    }
}
