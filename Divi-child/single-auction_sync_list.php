<?php
get_header(); // Load Divi Header

// Get current auction post ID and meta fields
$post_id = get_the_ID();
$auction_fields = get_post_meta($post_id);

// ✅ Extract fields safely
$auction_id = $auction_fields['_auction_auction_id'][0] ?? '';
$name = $auction_fields['_auction_name'][0] ?? 'Auction Name Not Available';
$status = $auction_fields['_auction_status'][0] ?? 'N/A';
$company_id = $auction_fields['_auction_company_id'][0] ?? 'N/A';
$items_count = $auction_fields['_auction_items_count'][0] ?? '0';
$description = $auction_fields['_auction_description'][0] ?? 'No Description';
$timezone = $auction_fields['_auction_timezone'][0] ?? 'America/Phoenix';

// ✅ Correctly Fetch Contact Information
$contact_name = trim(($auction_fields['_auction_coord_first_name'][0] ?? '') . " " . ($auction_fields['_auction_coord_last_name'][0] ?? '')) ?: 'N/A';
$contact_email = $auction_fields['_auction_coord_email'][0] ?? 'Email Not Found';
$contact_phone = $auction_fields['_auction_coord_phone'][0] ?? 'Phone Not Found';

// ✅ Fetch & unserialize images
$images = maybe_unserialize($auction_fields['_auction_featured_images'][0] ?? []);

// ✅ Fetch auction items from API
$items = [];
// Function should be defined at the top or outside the conditional block
function get_auction_terms($auction_data) {
  // Ensure 'terms' exists and is an array
  if (isset($auction_data['terms']) && is_array($auction_data['terms'])) {
      if (isset($auction_data['terms']['legalese'])) {
          return $auction_data['terms']['legalese']; // ✅ Return Terms & Conditions text
      }
  }
  return '<p>No terms and conditions available.</p>'; // Fallback message
}
function get_all_item_ids($auction_data) {
  $item_ids = [];

  if (!empty($auction_data['items_statuses']) && is_array($auction_data['items_statuses'])) {
      foreach ($auction_data['items_statuses'] as $item_status) {
          if (isset($item_status[0])) {
              $item_ids[] = $item_status[0]; // Extract only the ID
          }
      }
  }
  return $item_ids;
}
function fetch_additional_items($auction_id, $all_item_ids) {
  $batch_size = 50; // API supports fetching up to 50 at a time
  $all_additional_items = [];

  // Break item IDs into batches of 50
  $batches = array_chunk($all_item_ids, $batch_size);

  foreach ($batches as $batch) {
      $ids_string = implode(',', $batch);
      $api_url = "https://bidnow.auctionaz.com/api/auctions/{$auction_id}/items?strict_find=true&ids={$ids_string}";

      $response = wp_remote_get($api_url, [
          'timeout' => 60,
          'redirection' => 5,
          'blocking' => true,
          'headers' => ['Accept' => 'application/json'],
      ]);

      if (!is_wp_error($response)) {
          $items_data = json_decode(wp_remote_retrieve_body($response), true);
          if (!empty($items_data['items'])) {
              $all_additional_items = array_merge($all_additional_items, $items_data['items']);
          }
      }
  }

  return $all_additional_items;
}



if (!empty($auction_id)) {
//   var_dump($auction_id);
  $api_url = "https://bidnow.auctionaz.com/api/auctions/{$auction_id}?page=active";
  $response = wp_remote_get($api_url, [
      'timeout' => 60,
      'redirection' => 5,
      'blocking' => true,
      'headers' => ['Accept' => 'application/json'],
  ]);

  if (!is_wp_error($response)) {
      $auction_data = json_decode(wp_remote_retrieve_body($response), true);

      if (!empty($auction_data['items'])) {
          $items = $auction_data['items'];

          // ✅ Debugging Contact Info
          // var_dump($auction_data['contact_company']);
          $all_item_ids = get_all_item_ids($auction_data);

          // ✅ Get first 50 item IDs (avoid duplication)
          $existing_item_ids = array_column($items, 'id'); // Extract IDs from first 50 items

          // ✅ Check if we need to fetch more items

          if (count($all_item_ids) > 50) {
              $additional_items = fetch_additional_items($auction_id, $all_item_ids);

              // ✅ Remove duplicate items (avoid adding first 50 items again)
              $filtered_additional_items = array_filter($additional_items, function ($item) use ($existing_item_ids) {
                  return !in_array($item['id'], $existing_item_ids); // Only keep new items
              });

              $items = array_merge($items, $filtered_additional_items); // Merge unique items
           }
          // ✅ Update post meta with API contact info
          update_post_meta($post_id, '_auction_contact_company', $auction_data['contact_company'] ?? '');
          update_post_meta($post_id, '_auction_contact_email', $auction_data['contact_email'] ?? '');
          update_post_meta($post_id, '_auction_contact_phone', $auction_data['contact_phone'] ?? '');
      }
      if (!empty($auction_data['items_statuses'])) {
        // ✅ Get all item IDs
        $all_item_ids = get_all_item_ids($auction_data);

        // ✅ Store item IDs in post meta (if needed)
        // update_post_meta($post_id, '_auction_item_ids', $all_item_ids);

        // ✅ Debugging (REMOVE in production)
//         echo '<pre>';
//         print_r($all_item_ids);
//         echo '</pre>';
    }

      // ✅ Get Terms & Conditions Text
      $terms_conditions = get_auction_terms($auction_data);
  }
}


// ✅ Format auction end time
$scheduled_end_formatted = 'N/A';
if (!empty($auction_fields['_auction_scheduled_end_time'][0])) {
    try {
        $datetime = new DateTime($auction_fields['_auction_scheduled_end_time'][0], new DateTimeZone($timezone));
        $datetime->setTimezone(new DateTimeZone($timezone));
        $scheduled_end_formatted = strtoupper($datetime->format('M d')) . '<br>' . strtolower($datetime->format('g:ia T'));
    } catch (Exception $e) {
        error_log("DateTime Error: " . $e->getMessage());
    }
}

// Function to trim text to a specific number of words
function trim_text($text, $word_limit) {
  $words = explode(' ', $text);
  if (count($words) > $word_limit) {
      return implode(' ', array_slice($words, 0, $word_limit)) . '...';
  }
  return $text;
}
?>

<section class="mainSection">
  <div class="leftBar">
    <div class="leftBarNav">
      <p>AUCTION INFO</p>
    </div>
    <div class="mainInfoBox">
      <div class="sliderBox">
        <div class="auction-slider">
          <button class="slider-btn left-btn">&#10094;</button>
          <div class="auction-slider-for">
            <div class="auction-items-main">
              <?php if (!empty($images) && is_array($images)): ?>
                <img id="mainImage" src="<?php echo esc_url($images[0]); ?>" alt="Auction Image">
              <?php else: ?>
                <img id="mainImage" src="<?php echo get_stylesheet_directory_uri(); ?>/assets/default-image.jpg" alt="No Image Available">
              <?php endif; ?>
            </div>
          </div>
          <button class="slider-btn right-btn">&#10095;</button>

          <div class="auction-slider-nav">
            <?php if (!empty($images) && is_array($images)): ?>
              <?php foreach ($images as $image_url): ?>
                <div class="auction-items">
                  <img class="thumbnail" src="<?php echo esc_url($image_url); ?>" data-large="<?php echo esc_url($image_url); ?>">
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div style="padding: 15px;">
        <div class="primaryInfo">
          <div class="titleBox">
            <p><?php echo esc_html($name); ?></p>
            <p class="subTitle">by AuctionAZ.com, LLC <?php echo esc_html($company_id); ?></p>
          </div>
          <div class="calenderBox">
            <div class="calenderTop">
              <img src="<?php echo get_stylesheet_directory_uri(); ?>/assets/calendar.png" alt="Calendar" style="width: 13px; margin-right: 4px" />
              <p style="font-size: 13px">END</p>
            </div>
            <div class="schedule-date" style="padding: 4px 0">
              <p><?php echo $scheduled_end_formatted; ?></p>
            </div>
          </div>
        </div>
        <div style="display: flex; align-items: center">
          <div class="info-badge">
            <p><?php echo esc_html($items_count); ?> items</p>
          </div>
          <div class="info-badge">
            <p><?php echo esc_html($status); ?></p>
          </div>
        </div>
      </div>

      <div class="mainTabBox">
        <div style="display: flex; box-shadow: 3px 6px 5px 0px #eee">
          <div class="tab active" onclick="changeTab(this, 'details')">
            <p class="tabTitle">DETAILS</p>
          </div>
          <div class="tab" onclick="changeTab(this, 'terms')">
            <p class="tabTitle">TERMS</p>
          </div>
          <div class="tab" onclick="changeTab(this, 'contact')">
            <p class="tabTitle">CONTACT</p>
          </div>
        </div>

        <div class="tabContent active" id="details">
          <?php echo wp_kses_post($description); ?>
        </div>
          <div class="tabContent" id="terms">
              <?php echo wp_kses_post($terms_conditions); ?>

          </div>

        <div class="tabContent" id="contact">
          <h3>Contact Information</h3>
          <p><strong>Name:</strong> <?php echo esc_html($contact_name); ?></p>
          <p><strong>Email:</strong> <a href="mailto:<?php echo esc_attr($contact_email); ?>"><?php echo esc_html($contact_email); ?></a></p>
          <p><strong>Phone:</strong> <a href="tel:<?php echo esc_attr($contact_phone); ?>"><?php echo esc_html($contact_phone); ?></a></p>
        </div>
      </div>
    </div>
  </div>

  <div class="rightSection">
        <div class="rightSectionNav">
          <div style="display: flex">
            <div class="menuIconBox">
              <div class="menuIcon">
                <img src="<?php echo get_stylesheet_directory_uri(); ?>/assets/menuIcon.png" alt="" />
              </div>
            </div>
            <div class="diagonalSeperator"></div>
            <div style="height: 100%; display: flex; align-items: center">
              <p
                style="
                  font-size: 14px;
                  color: rgba(0, 0, 0, 0.48);
                  cursor: pointer;
                "
              >
                All
              </p>
            </div>
          </div>
          <div style="display: flex; height: 100%; column-gap: 15px">
            <div class="printerBox">
              <img src="<?php echo get_stylesheet_directory_uri(); ?>/assets/printing.png" alt="" style="width: 20px" />
              <p style="font-size: 14px; color: rgba(0, 0, 0, 0.48)">PRINT</p>
            </div>
            <div class="shareBox">
              <img src="<?php echo get_stylesheet_directory_uri(); ?>/assets/share.png" alt="" style="width: 20px" />
              <p style="font-size: 14px; color: rgba(0, 0, 0, 0.48)">SHARE</p>
            </div>
          </div>
        </div>

        <div class="rightProductMainBox">
          <div class="rightProductBox">
            <div class="searchBarBox">
              <img src="<?php echo get_stylesheet_directory_uri(); ?>/assets/search.png" alt="Search" width="28px" />
              <input
                type="text"
                placeholder="Search"
                class="searchInput"
                id="searchInput"
              />
              <div class="backSpaceBox" id="backSpaceBox">
                <img
                  src="<?php echo get_stylesheet_directory_uri(); ?>/assets/backspacearrow.png"
                  alt="backspace"
                  width="20px"
                  id="backSpaceImg"
                />
              </div>
              <div
                style="height: 100%; width: 1px; background-color: #e0e0e0"
              ></div>
              <img src="<?php echo get_stylesheet_directory_uri(); ?>/assets/sort.png" alt="Search" width="25px" />
            </div>
            <div class="productCount">
              <p>No items found.</p>
            </div>


            <div class="auction-items-container">
                <?php if (!empty($items)): ?>
                  <?php foreach ($items as $item): ?>
                    <div class="productMainBox">
                      <div class="productBox">
                          <div class="productImgBox" style="background-image: url('<?php echo esc_url($item['images'][0]['small_url'] ?? get_stylesheet_directory_uri() . "/assets/default-image.jpg"); ?>')">
                            <div class="productImgIconsBox">
                              <img src="<?php echo get_stylesheet_directory_uri(); ?>/assets/star.png" alt="star" width="25px">
                              <img src="<?php echo get_stylesheet_directory_uri(); ?>/assets/shareWhite.png" alt="share" width="25px">
                            </div>
                            <div class="tagBox"><p><?php echo esc_html(ucfirst($item['status'] ?? 'N/A')); ?></p></div>
                          </div>
                        <div class="productDetailBox">
                        <p class="productTitle">
                            <?php
                            $trimmed_name = trim_text($item['name_with_prefix'] ?? 'Item Name Not Available', 5);
                            echo esc_html($trimmed_name);
                            ?>
                        </p>
                        <p class="productSubTitle">
                            <?php
                            $trimmed_description = trim_text($item['description_without_html'] ?? 'No Description Available', 40);
                            echo esc_html($trimmed_description);
                            ?>
                        </p>
                        <p class="priceTag">High bid
                          $<?php echo number_format($item['api_bidding_state']['closing_bid']['amount'] ?? 0); ?>
                        </p>
                          <div class="priceBox">
                            <p style="font-size: 0.9rem">SOLD</p>
                            <p style="font-size: 0.7rem">$<?php echo number_format($item['api_bidding_state']['closing_bid']['amount'] ?? 0); ?></p>
                          </div>
                        </div>
                      </div>


                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
              <p>No items found for this auction.</p>
              <?php endif; ?>
            </div>



        </div>
      </div>
</section>

<?php get_footer(); ?>