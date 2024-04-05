<?php
/*
 * Plugin Name: Sitemap Plugin
 * Description: Вывод отзывов с товаров категории на саму страницу категории
 * Text Domain: sitemap-lg-plugin
 */

defined('ABSPATH') or die('Something wrong');

class SitemapLgPlugin
{
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'page_init'));
    }

    public function add_plugin_page()
    {
        add_submenu_page(
                'options-general.php',
                'Sitemap Generator',
                'Sitemap Generator',
                'manage_options',
                'sitemap-plugin',
                array($this, 'create_admin_page')
        );
    }

    public function create_admin_page()
    {
        ?>
        <div class="wrap">
            <h2>Sitemap Generator</h2>

            <form method="post" action="">

                <?php
                echo '<label for="sitemap_filename">Sitemap Filename:</label>';
                echo '<input type="text" id="sitemap_filename" name="sitemap_filename" value="' . esc_attr(get_option('sitemap_filename')) . '" />';

                echo '<h3>Pages:</h3>';
                $pages = get_pages();
                echo '<label><input type="checkbox" id="select_all_pages" /> Select All Pages</label><br>';
                foreach ($pages as $page) {
                    $checked = get_option('sitemap_pages') && in_array($page->ID, get_option('sitemap_pages')) ? 'checked="checked"' : '';
                    echo '<label><input type="checkbox" name="sitemap_pages[]" value="' . $page->ID . '" ' . $checked . '> ' . $page->post_title . '</label><br>';
                }

                // Вывод чекбоксов для выбора других типов записей
                echo '<h3>Other Post Types:</h3>';
                $post_types = get_post_types(array('public' => true), 'objects');
                echo '<label><input type="checkbox" id="select_all_post_types" /> Select All Post Types</label><br>';
                foreach ($post_types as $post_type) {
                    if ($post_type->name !== 'attachment' && $post_type->name !== 'page') {
                        $checked = get_option('sitemap_post_types') && in_array($post_type->name, get_option('sitemap_post_types')) ? 'checked="checked"' : '';
                        echo '<label><input type="checkbox" name="sitemap_post_types[]" value="' . $post_type->name . '" ' . $checked . '> ' . $post_type->label . '</label><br>';
                    }
                }

                ?>
                <script>
                    // Выбор или отмена выбора всех страниц
                    document.getElementById('select_all_pages').addEventListener('change', function() {
                        var checkboxes = document.querySelectorAll('input[name="sitemap_pages[]"]');
                        checkboxes.forEach(function(checkbox) {
                            checkbox.checked = document.getElementById('select_all_pages').checked;
                        });
                    });

                    // Выбор или отмена выбора всех типов записей
                    document.getElementById('select_all_post_types').addEventListener('change', function() {
                        var checkboxes = document.querySelectorAll('input[name="sitemap_post_types[]"]');
                        checkboxes.forEach(function(checkbox) {
                            checkbox.checked = document.getElementById('select_all_post_types').checked;
                        });
                    });
                </script>

                <?php

                // Вывод чекбоксов для выбора таксономий
                $taxonomies = get_taxonomies(array('public' => true), 'objects');
                foreach ($taxonomies as $taxonomy) {
                    // Проверяем, что это не категория и что у таксономии есть хотя бы один термин
                    if ($taxonomy->name !== 'category' && !empty(get_terms($taxonomy->name))) {
                        echo '<h3>' . $taxonomy->label . ':</h3>';
                        echo '<label><input type="checkbox" id="select_all_' . $taxonomy->name . '" /> Select All ' . $taxonomy->label . '</label><br>'; // Опция "Выбрать все" для таксономий
                        $terms = get_terms($taxonomy->name, array('hide_empty' => false));
                        echo '<ul>';
                        foreach ($terms as $term) {
                            $checked = isset($_POST['sitemap_taxonomies']) && in_array($taxonomy->name . '|' . $term->slug, $_POST['sitemap_taxonomies']) ? 'checked="checked"' : '';
                            echo '<li><label><input type="checkbox" name="sitemap_taxonomies[]" value="' . $taxonomy->name . '|' . $term->slug . '" ' . $checked . '> ' . $term->name . '</label></li>';
                        }
                        echo '</ul>';

                        // JavaScript для "Выбрать все" опции для каждой таксономии
                        ?>
                        <script>
                            document.getElementById('select_all_<?php echo $taxonomy->name; ?>').addEventListener('change', function() {
                                var checkboxes = document.querySelectorAll('input[name="sitemap_taxonomies[]"]');
                                checkboxes.forEach(function(checkbox) {
                                    if (checkbox.value.startsWith('<?php echo $taxonomy->name; ?>')) {
                                        checkbox.checked = document.getElementById('select_all_<?php echo $taxonomy->name; ?>').checked;
                                    }
                                });
                            });
                        </script>
                        <?php
                    }
                }

                submit_button('Generate Sitemap', 'primary', 'generate_sitemap');
                ?>
            </form>
        </div>
        <?php
    }

    public function page_init()
    {
        if (isset($_POST['generate_sitemap'])) {
            if (isset($_POST['sitemap_pages'])) {
                update_option('sitemap_pages', $_POST['sitemap_pages']);
            } else {
                update_option('sitemap_pages', array());
            }
            if (isset($_POST['sitemap_post_types'])) {
                update_option('sitemap_post_types', $_POST['sitemap_post_types']);
            } else {
                update_option('sitemap_post_types', array());
            }
            if (isset($_POST['sitemap_taxonomies'])) {
                update_option('sitemap_taxonomies', $_POST['sitemap_taxonomies']);
            } else {
                update_option('sitemap_taxonomies', array());
            }

            // Сохранение названия файла сайтмапа
            if (isset($_POST['sitemap_filename'])) {
                update_option('sitemap_filename', sanitize_text_field($_POST['sitemap_filename']));
            }

            $this->generate_sitemap();
        }

    }


    public function generate_sitemap()
    {
        $pages = get_option('sitemap_pages');
        $post_types = get_option('sitemap_post_types');
        $taxonomies = get_option('sitemap_taxonomies');

        if (empty($pages) && empty($post_types) && empty($taxonomies)) {
            echo '<div class="error"><p>Please select at least one page, post type, or taxonomy.</p></div>';
            return;
        }

        $sitemap = '<?xml version="1.0" encoding="UTF-8"?>';
        $sitemap .= '<urlset xmlns="https://www.sitemaps.org/schemas/sitemap/0.9" xmlns:video="https://www.google.com/schemas/sitemap-video/1.1" xmlns:image="https://www.google.com/schemas/sitemap-image/1.1">'; // Определение пространства имен для image

        // Генерация страниц
        foreach ($pages as $page_id) {
            $page = get_post($page_id);
            if ($page) {
                $post_id        = $page->ID;
                $post_title     = get_the_title($post_id);
                $post_title     = str_replace('–', '&#8211;', $post_title);
                $post_image     = get_the_post_thumbnail_url($post_id);

                if ($post_image) {
                    $post_image = remove_query_arg('v', $post_image);
                }

                $last_modified  = get_the_modified_date('Y-m-d\TH:i:sP', $post_id);
                $post_permalink = get_permalink($post_id);

                $sitemap .= '<url>';
                $sitemap .= '<lastmod>' . htmlspecialchars($last_modified) . '</lastmod>';
                $sitemap .= '<loc>' . esc_url($post_permalink) . '</loc>';
                $sitemap .= '</url>';

            }
        }

// Генерация других типов записей
        foreach ($post_types as $post_type) {
            $args = array(
                    'post_type'      => $post_type,
                    'posts_per_page' => -1,
            );
            $posts = get_posts($args);

            foreach ($posts as $post) {
                $post_id        = $post->ID;
                $post_title     = get_the_title($post_id);
                $post_title     = str_replace('–', '&#8211;', $post_title);
                $post_image     = get_the_post_thumbnail_url($post_id);
                $last_modified = get_the_modified_date('Y-m-d\TH:i:sP', $post_id);
                $post_permalink = get_permalink($post_id);
                $post_content   = $post->post_content;

                if ($post_image) {
                    $post_image = remove_query_arg('v', $post_image);
                }
                if ($post_type === 'product') {
                    preg_match('/\[video.*?mp4="(.*?)".*?\]/', $post_content, $matches);
                    if (!empty($matches)) {
                        $video_url = $matches[1];
                        $video_url = remove_query_arg('v', $video_url);

                        $sitemap .= '<url>';
                        $sitemap .= '<loc>' . esc_url($post_permalink) . '</loc>';
                        if ($post_image) {
                            $sitemap .= '<image:image>';
                            $sitemap .= '<image:loc>'. htmlspecialchars($post_image) .'</image:loc>';
                            $sitemap .= '</image:image>';
                        }
                        $sitemap .= '<video:video>';
                        $sitemap .= '<video:title>' . htmlspecialchars($post_title) . '</video:title>';
                        $sitemap .= '<video:content_loc>';
                        $sitemap .= htmlspecialchars($video_url);
                        $sitemap .='</video:content_loc>';
                        $sitemap .= '</video:video>';
                        $sitemap .= '<lastmod>' . htmlspecialchars($last_modified) . '</lastmod>';
                        $sitemap .= '</url>';
                    }
                    else {
                        $sitemap .= '<url>';
                        $sitemap .= '<loc>' . esc_url($post_permalink) . '</loc>';
                        if ($post_image) {
                            $sitemap .= '<image:image>';
                            $sitemap .= '<image:loc>'. htmlspecialchars($post_image) .'</image:loc>';
                            $sitemap .= '</image:image>';
                        }
                        $sitemap .= '<lastmod>' . htmlspecialchars($last_modified) . '</lastmod>';
                        $sitemap .= '</url>';
                    }
                } else {

                    $sitemap .= '<url>';
                    $sitemap .= '<lastmod>' . htmlspecialchars($last_modified) . '</lastmod>';
                    $sitemap .= '<loc>' . esc_url($post_permalink) . '</loc>';
                    $sitemap .= '</url>';
                }
            }
        }

        // Генерация таксономий
        foreach ($taxonomies as $taxonomy_term) {
            list($taxonomy, $term_slug) = explode('|', $taxonomy_term);
            $term = get_term_by('slug', $term_slug, $taxonomy);

            if ($term) {
                $sitemap .= '<url>';
                $sitemap .= '<lastmod>' . htmlspecialchars(date('Y-m-d\TH:i:sP')) . '</lastmod>';
                $sitemap .= '<loc>' . esc_url(get_term_link($term, $taxonomy)) . '</loc>';
                $sitemap .= '</url>';
            }
        }




        $sitemap .= '</urlset>';

        $sitemap_filename = get_option('sitemap_filename') . '.xml';
        $sitemap_file  = ABSPATH . '/' . $sitemap_filename;
        file_put_contents($sitemap_file, $sitemap);
        echo '<div class="updated"><p>Sitemap generated successfully. You can download it <a href="' . esc_url(home_url('/' . $sitemap_filename)) . '">here</a>.</p></div>';

    }
}


if (class_exists('SitemapLgPlugin')) {
    $vladPlugin = new SitemapLgPlugin();
}
