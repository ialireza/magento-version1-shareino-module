<?php

class Shareino_Sync_Helper_Data extends Mage_Core_Helper_Abstract
{
    const SHAREINO_API_URL = "http://shareino.ir/api/v1/public/";

    /**
     * Called when need to send request to external server or site
     *
     * @param $url url address af Server
     * @param null $body content of request like product
     * @param $method
     * @return mixed | null
     */
    public function sendRequset($url, $body = null, $method)
    {

        // Init curl
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        // Generate url and set method in url
        $url = self::SHAREINO_API_URL . $url;
        curl_setopt($curl, CURLOPT_URL, $url);

        // Set method in curl
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);

        // Get token from site setting
        $SHAREINO_API_TOKEN = Mage::getStoreConfig("shareino/SHAREINO_API_TOKEN");


        // Check if token has been set then send request to {@link http://shareino.com}
        if (!empty($SHAREINO_API_TOKEN)) {

            // Set Body if its exist
            if ($body != null) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
            }
//            curl_setopt($curl, CURLOPT_HEADER, true);    // we want headers

            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                    "Authorization:Bearer $SHAREINO_API_TOKEN"),
                    "User-Agent:Magento_Module_0.2.1"
            );

            // Get result
            $result = curl_exec($curl);;

            // Get Header Response header
            $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            if ($httpcode != 200) {

                if ($httpcode == 401 || $httpcode == 403) {
                    Mage::getSingleton('core/session')->addError(Mage::helper("sync")
                        ->__("خطا ! لطفا صحت توکن و وضعیت دسترسی به وب سرویس شیرینو را بررسی کنید"));
                }
                return null;
            }
            return $result;
        } else {
            Mage::getSingleton('core/session')->addError(Mage::helper("sync")->__("توکن وارد نشده است"));
            return null;
        }
        return null;
    }



    public function getAllProductIds()
    {
        $ids = array();

        $collection = Mage::getModel('catalog/product')->getCollection();
        $collection->addAttributeToFilter('status', 1);
        $collection->addFieldToFilter(array(array('attribute' => 'visibility', 'neq' => "1")));
        $collection->addAttributeToFilter('entity_id', array('nin' => $this->getConfSimpleProduct()));

        $_productCollection = $collection->load();

        foreach ($_productCollection as $product) {
            $ids[] = $product->getId();
        }
        return $ids;
    }

    public function getConfigurableProduct()
    {
        $ids = array();

        $collection = Mage::getModel('catalog/product')->getCollection();
        $collection->addAttributeToFilter('type_id', 'configurable');
        $collection->addAttributeToFilter('status', 1);
        $collection->addFieldToFilter(array(array('attribute' => 'visibility', 'neq' => "1")));

        $_productCollection = $collection->load();

        foreach ($_productCollection as $product) {
            $ids[] = $product->getId();
        }
        return $ids;
    }

    public function getConfSimpleProduct()
    {

        $ids = [];
        /**
         * Get the resource model
         */
        $resource = Mage::getSingleton('core/resource');

        /**
         * Retrieve the read connection
         */
        $readConnection = $resource->getConnection('core_read');

        $query = 'SELECT child_id FROM ' . $resource->getTableName('catalog/product_relation')
            . ' WHERE parent_id in ( ' . implode(" ,", $this->getConfigurableProduct()) . ');';


        /**
         * Execute the query and store the results in $results
         */
        $results = $readConnection->fetchAll($query);

        foreach ($results as $product) {
            $ids[] = $product['child_id'];
        }

        return $ids;
    }

    public function getProductById($productId)
    {
        $product = Mage::getModel('catalog/product')->load($productId);
        return $this->getProductDetail($product);
    }

    public function getProductDetail($product)
    {

        $attrs = $product->getData();

        $variations = array();

        if ($product->isConfigurable()) {

            $configurableProduct = $product->getTypeInstance(true);
            $confAttrs = $configurableProduct->getConfigurableAttributesAsArray($product);

            $chileds = $configurableProduct->getChildrenIds($product->entity_id);

            foreach ($chileds[0] as $child) {
                $children = Mage::getModel('catalog/product')->load($child);
//                $this->j($children->getData());
                if ($children) {
                    $stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($children);
                    $var = array(
                        "code" => $child,
                        "quantity" => $stock->getQty() >= 0 ? $stock->getQty() : 0,
                        "sku" => $children->getData("sku"),
                        "price" => $children->getData("price")
                    );

                    $attribute = array();
                    foreach ($confAttrs as $conf) {
                        $value = $children->getResource()
                            ->getAttribute($conf["attribute_code"])
                            ->getSource()
                            ->getOptionText($children->getData($conf["attribute_code"]));;
                        $attribute[$conf["attribute_code"]] = array(
                            "label" => $conf["frontend_label"],
                            "value" => $value
                        );
                    }

                    $var["variation"] = $attribute;
                    $variations[] = $var;
                }

            }
        }


        $stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);

        $gallery_images = $product->getMediaGalleryImages();
        $galleris = array();
        foreach ($gallery_images->getItems() as $g_image) {
            $galleris[] = $g_image['url'];

        }
        $product_json = array(
            "name" => $attrs["name"],
            "code" => $attrs["entity_id"],
            "sku" => $attrs["sku"],
            "price" => isset($attrs["price"]) ? $attrs["price"] : null,
            "sale_price" => $attrs["sku"],
            "discount" => "",
            "quantity" => $stock->getQty() >= 0 ? $stock->getQty() : 0,
            "weight" => isset($attrs["weight"]) ? $attrs["weight"] : null,
            "url" => $product->getProductUrl(),
            "brand_id" => "",
            "categories" => "",
            "short_content" => $attrs["short_description"],
            "long_content" => $attrs["description"],
            "meta_keywords" => "",
            "meta_description" => "",
            "image" => Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'catalog/product' . $product->getImage(),
            "images" => $galleris,
            "attributes" => ""

        );

        $removeKeys = array(
            "recurring_profile",
            "entity_id",
            "entity_type_id",
            "attribute_set_id",
            "type_id",
            "sku",
            "has_options",
            "required_options",
            "created_at",
            "updated_at",
            "name",
            "url_key",
            "msrp_enabled",
            "msrp_display_actual_price_type",
            "meta_title",
            "meta_description",
            "image",
            "small_image",
            "thumbnail",
            "custom_design",
            "page_layout",
            "options_container",
            "gift_message_available",
            "url_path",
            "weight",
            "price",
            "special_price",
            "msrp",
            "status",
            "visibility",
            "tax_class_id",
            "is_recurring",
            "description",
            "short_description",
            "meta_keyword",
            "custom_layout_update",
            "news_from_date",
            "news_to_date",
            "special_from_date",
            "special_to_date",
            "custom_design_from",
            "custom_design_to",
            "group_price",
            "group_price_changed",
            "media_gallery",
            "tier_price",
            "tier_price_changed",
            "stock_item",
            "is_in_stock",
            "is_salable",
            "meta_keywords",
            "meta_description",
            "image_label",
            "thumbnail_label",
            "small_image_label"
        );

        foreach ($removeKeys as $key) {
            unset($attrs[$key]);
        }

        $customAttrs = array();
        foreach ($attrs as $key => $value) {
            $customAttrs[$key] = array(

                'label' => $product->getResource()->getAttribute($key)->getFrontend()->getLabel($product),
                'value' => $attrs[$key]
            );
        }
        $product_json["attributes"] = $customAttrs;
        $product_json["variants"] = $variations;

        $catsIDs = $product->getCategoryIds();


        $cats = array();
        foreach ($catsIDs as $category_id) {
            $_cat = Mage::getModel('catalog/category')->load($category_id);
            $cats[] = array(
                "id_category" => $_cat->getId(),
                "link_rewrite" => $_cat->getUrlKey(),
                "name" => $_cat->getName(),
            );

        }
        $cats = self::getShareinoids($cats);
        $product_json["categories"] = $cats;


        return $product_json;
    }


    public static function getShareinoids($categories)
    {

        $categoriesIds = array();
        $productCategories = array(
            "matching" => array(),
            "notMatching" => array()
        );

        $notmatching = array();
        foreach ($categories as $category) {
            $categoriesIds[] = $category["id_category"];
            $notmatching[$category["id_category"]]
                = array($category["link_rewrite"] => $category["name"]);
        }


        $collection = Mage::getModel('sync/organize')->getCollection();

        $collection->addFieldToFilter('cat_id', array('in' => $categoriesIds));

        $result = $collection->load()->getData();

        if ($result) {
            foreach ($result as $item) {
                $productCategories["matching"] = array_merge(
                    $productCategories["matching"],
                    explode(",", $item["ids"])
                );

                unset($notmatching[$item["cat_id"]]);
            }
        }
//        d($notmatching);
        foreach ($notmatching as $item) {
            $key = key($item);
            $productCategories["notMatching"][$key] = $item[$key];
        }

        return $productCategories;

    }

    function dieObject($object, $kill = true, $json = false)
    {
        echo '<xmp style="text-align: left;">';
        if ($json)
            echo json_encode($object);
        else
            print_r($object);
        echo '</xmp><br />';

        if ($kill) {
            die('END');
        }

        return $object;
    }

    public function d($object, $json)
    {
        $this->dieObject($object, true, $json);

    }

    public function j($object)
    {
        echo json_encode($object);
        die;
    }

    public function p($object)
    {
        $this->dieObject($object, false);

    }

    public function v($object)
    {

        echo '<xmp style="text-align: left;">';
        var_dump($object);
        echo '</xmp><br />';
        return $object;
    }

}