<?php

    public function restaurant_menu_new()
    {
        $response = array();
        $data = $this->postData;
        $curr = get_currency($data['restaurant_id']);
        if (isset($data) && !empty($data) && !empty($data['restaurant_id'])) 
        {
            if( (!isset($data['delivery_type'])) || $data['delivery_type']=="" || $data['delivery_type']==0){
                $data['delivery_type'] = DELIVERY_TYPES_DINEIN;
            }
            $delivery_type = getDeliveryType($data['delivery_type']);
            $this->load->model('fooditems_model');
            $tmp = $this->fooditems_model->getFoodItemsWithCategory($data['restaurant_id'],getDeliveryType($delivery_type));
            
            if(!empty($tmp))
            {
                $menu_data = [];
                $catname = [];
                foreach($tmp as $key => $value)
                {   
                    
                    if($value['discount_type'] == RESTAURANT_DISCOUNT_ON_FOOD_ITEM && !empty($value['discount']))
                    {  
                        $value['original_price'] = $value['price'];
                        $value['price'] = $value['discount'];
                    }else
                    {
                        $value = originalAndDiscountPrice($value['price'],$value['discount'],$value['discount_type']) + $value;
                        $value['price'] = round($value['price'],$this->uptodecimal);
                    }
                    
                    if(!empty($value['original_price']))
                    {
                        $value['original_price'] = round($value['original_price'],$this->uptodecimal);
                    }
                    $food_item_id = $value['id'];
                    $data = $this->restaurant_attributes_new($food_item_id);
                    $value['attributes'] = $data;
                    $value['sr_no'] = $sr_no[$value['food_category_name']];
                    $menu_data[$value['food_category_name']][] = $value;
                    if(!in_array($value['food_category_name'], $catname)){
                        $catname[] = $value['food_category_name'];
                    }   
                    
                }
                //To create category wise serial number
                $sr_no = 1;
                foreach($menu_data as $key => $items)
                {
                    $data = [];
                    foreach($items as  $item)
                    {
                        $item['sr_no'] = $sr_no;
                        $data[] = $item;
                        $sr_no++;
                    }
                    $menu_data[$key] = $data;
                }
                $response['flag'] = 1;
                $response['image_base_path'] = base_url(FOOD_ITEMS_IMG_PATH);
                $response['currency_type'] = html_entity_decode($curr["symbol"]);
                $response['data'] = $menu_data;
                $response['cat_names'] = $catname;
            }
            else
            {
                $response['flag'] = 1;
                $response['message'] = $this->lang->line('no_records');
            }
        }
        else
        {
            $response['flag'] = 0;
            $response['user_data'] = array();
            $response['message'] = $this->lang->line('invalid_request');
        }
        return $response;
    }

    function originalAndDiscountPrice($price,$discount,$discount_type,$decimals=2)
{
    $original_price = $price;
    if($discount_type == RESTAURANT_DISCOUNT_TYPE_PERCENTAGE)
    {
        //$discount_amount = $price - $discounted_price;
        //$discount = $discount_per = ($price * $discount_amount) / 100;
        //$price = $discounted_price;
        $discount = truncate_number((($price * $discount) / 100),$decimals);
        $price = $price - $discount;
    }else if($discount_type == RESTAURANT_DISCOUNT_TYPE_FIXED){
        //$discount = $discount_amount = $price - $discounted_price;
        //$price = $discounted_price;
        $price = $price - truncate_number($discount,$decimals);
    }
    if(!empty($decimals)){
        $original_price = round($original_price,$decimals);
        $price = round($price,$decimals);
    }
    if($original_price != $price)
    {
        return ["original_price" =>$original_price , "price" => $price, 'discount'=>$discount];
    }else
    {
        return ["price" => $price];
    }
}


public function restaurant_attributes_new($food_item_id = null,$translations=0)
{
    $response = array();
    $data = $this->postData;
    $trans_lang = (!empty($data['lang_code']))?$data['lang_code']:'en';
    if(!empty($food_item_id) && $food_item_id!=null){
        $data['food_item_id'] = $food_item_id;
    }
    $response['data'] = [];
    if (isset($data) && !empty($data) && !empty($data['food_item_id'])) 
    {
        $this->load->model('food_item_attribute_values_model');
        $tmp =  $this->food_item_attribute_values_model->getAttributes($data['food_item_id'],'',$trans_lang,$translations,1);
        //Format 
        $curr["symbol"] = "";
        if(!empty($tmp))
        {
            foreach($tmp as $key => $t)
            {   
                if($curr["symbol"] == ""){
                    $curr = get_currency($t->restaurant_id);
                }
                $records[$t->attribute_id]['id']  = $t->attribute_id;
                $records[$t->attribute_id]['attribute_type']  = $t->attribute_type;
                $records[$t->attribute_id]['att_value_id']  = $t->att_value_id;
                $records[$t->attribute_id]['is_required']  = $t->is_required;
                $records[$t->attribute_id]['attribute_name'] = $t->attribute_name;
                if($trans_lang!="en"){
                    if($t->trans_attribute_name!=""){
                        $records[$t->attribute_id]['attribute_name'] = $t->trans_attribute_name;
                    }
                }
                if(!empty($records[$t->attribute_id]['total_values_counter']))
                {
                    $records[$t->attribute_id]['total_values_counter']++;
                }else
                {
                    $records[$t->attribute_id]['total_values_counter'] = 1;
                }
                $t->price = round($t->price,$this->uptodecimal);
                
                $attribute_name = $t->attribute_name;
                if($trans_lang!="en"){
                    if($t->trans_attribute_value!=""){
                        $t->attribute_name = $attribute_name = $t->trans_attribute_name;
                        $t->attribute_value = $t->trans_attribute_value;
                    }
                }
                if($t->is_required)
                {
                    $records[$t->attribute_id]['options'][] = $t;
                }else
                {
                    if(!isset($records[$t->attribute_id]['options']) && StaticArrays::$attributes_types[$records[$t->attribute_id]['attribute_type']]!="Checkbox")
                    {       
                        $records[$t->attribute_id]['options'][] = (object)[
                            'id' => 0,
                            'food_item_id' => $t->food_item_id,
                            'attribute_id' => $t->attribute_id,
                            'attribute_value_id' => 0,
                            'attribute_type' => $t->attribute_type,
                            'is_required' => $t->is_required,
                            'price' => 0,
                            'restaurant_id' => $t->restaurant_id,
                            'attribute_value' => 'None',
                            'attribute_name' =>  $attribute_name,
                            'tax_amount' => 0
                        ];
                    }
                    $records[$t->attribute_id]['options'][] = $t;

                }
                //If attribute type dropdown the set list of that dropdown
                if($t->attribute_type == 3)
                {
                    $records[$t->attribute_id]['attribute_value_list'][$t->attribute_value_id] = $t->attribute_value . ' (+' . html_entity_decode($curr["symbol"]) . $t->price . ')';
                }
            }    
            //debug($records); exit; 
            $records = array_values($records); 
            if($curr["symbol"] == ""){
                $curr = get_currency(0);
            }
            $response['flag'] = 1;
            $response['data'] = $records;
            $response['currency_type'] = html_entity_decode($curr["symbol"]);
            $response['attribute_types'] = StaticArrays::$attributes_types; 
        }
        else
        {
            $response['flag'] = 0;
            $response['message'] = $this->lang->line('no_records');
        }
    }
    return $response['data'];
}