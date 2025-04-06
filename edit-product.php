// Set product properties
        $product->id = $product_id;
        $product->name = $form_data['name'];
        $product->description = $form_data['description'];
        $product->price = $form_data['price'];
        $product->stock = $form_data['stock'];
        $product->category_id = $form_data['category_id'];
        $product->image = $image_name;
        $product->is_customizable = $form_data['customizable'];
        $product->status = $form_data['featured'] === '1' ? 'featured' : 'active';
        
        // Don't use categories array which causes the foreign key error
        $product->categories = [];
        
        // Update product
        if ($product->update()) {

// Replace with:

// Set product properties
        $product->id = $product_id;
        $product->name = $form_data['name'];
        $product->description = $form_data['description'];
        $product->price = $form_data['price'];
        $product->stock = $form_data['stock'];
        $product->category_id = $form_data['category_id'];
        $product->image = $image_name;
        $product->is_customizable = $form_data['customizable'];
        $product->status = $form_data['featured'] === '1' ? 'featured' : 'active';
        
        // Don't use categories array which causes the foreign key error
        $product->categories = [];
        
        // Update product
        if ($product->update()) {
        } 