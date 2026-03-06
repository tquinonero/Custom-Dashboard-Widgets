# Plugin Check Report

**Plugin:** Custom Dashboard Widgets
**Generated at:** 2026-03-06 22:23:10


## `includes/services/class-cdw-cli-service.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 108 | 21 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter esc_like( $table_name ) . &quot;&#039;&quot; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- esc_like sanitizes the value; SHOW TABLES does not support placeholders.\n\t\t\t) used in $wpdb-&gt;get_var(&quot;SHOW TABLES LIKE &#039;&quot; . $wpdb-&gt;esc_like( $table_name ) . &quot;&#039;&quot;) |  |
| 108 | 23 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 108 | 23 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 117 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 303 | 82 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 332 | 61 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 1789 | 25 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 1835 | 27 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 1835 | 27 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 1849 | 27 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 1849 | 27 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 1949 | 28 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 1949 | 28 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 1985 | 31 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 1985 | 31 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 2194 | 19 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 2194 | 19 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 2209 | 22 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $table_name_escaped used in $wpdb-&gt;get_results(&quot;SHOW COLUMNS FROM \`$table_name_escaped\`&quot;)\n$table_name_escaped assigned unsafely at line 2205:\n $table_name_escaped = preg_replace( &#039;/[^a-zA-Z0-9_]/&#039;, &#039;&#039;, $table_name )\n$table_name assigned unsafely at line 2204:\n $table_name = $table[0] |  |
| 2209 | 24 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 2209 | 24 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 2224 | 23 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $table_name_escaped used in $wpdb-&gt;get_results($wpdb-&gt;prepare(\n\t\t\t\t\t\t\t&quot;SELECT COUNT(*) as cnt FROM \`$table_name_escaped\` WHERE \`$col_name_escaped\` LIKE %s&quot;, \t\t\t\t\t\t\t&#039;%&#039; . $wpdb-&gt;esc_like( $old ) . &#039;%&#039;\n\t\t\t\t\t\t))\n$table_name_escaped assigned unsafely at line 2205:\n $table_name_escaped = preg_replace( &#039;/[^a-zA-Z0-9_]/&#039;, &#039;&#039;, $table_name )\n$col_name_escaped assigned unsafely at line 2213:\n $col_name_escaped = preg_replace( &#039;/[^a-zA-Z0-9_]/&#039;, &#039;&#039;, $col_name )\n$table_name assigned unsafely at line 2204:\n $table_name = $table[0]\n$col_name assigned unsafely at line 2212:\n $col_name = $column[0]\n$column[0] used without escaping. |  |
| 2224 | 31 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 2224 | 31 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 2246 | 32 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $table_name_escaped used in $wpdb-&gt;query($wpdb-&gt;prepare(\n\t\t\t\t\t\t\t\t&quot;UPDATE \`$table_name_escaped\` SET \`$col_name_escaped\` = REPLACE(\`$col_name_escaped\`, %s, %s) WHERE \`$col_name_escaped\` LIKE %s&quot;, \t\t\t\t\t\t\t\t$old,\n\t\t\t\t\t\t\t\t$new,\n\t\t\t\t\t\t\t\t&#039;%&#039; . $wpdb-&gt;esc_like( $old ) . &#039;%&#039;\n\t\t\t\t\t\t\t))\n$table_name_escaped assigned unsafely at line 2205:\n $table_name_escaped = preg_replace( &#039;/[^a-zA-Z0-9_]/&#039;, &#039;&#039;, $table_name )\n$col_name_escaped assigned unsafely at line 2213:\n $col_name_escaped = preg_replace( &#039;/[^a-zA-Z0-9_]/&#039;, &#039;&#039;, $col_name )\n$table_name assigned unsafely at line 2204:\n $table_name = $table[0]\n$col_name assigned unsafely at line 2212:\n $col_name = $column[0]\n$column[0] used without escaping. |  |
| 2246 | 43 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 2246 | 43 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 2261 | 21 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $pk_col used in $wpdb-&gt;get_results($wpdb-&gt;prepare(\n\t\t\t\t\t\t\t&quot;SELECT \`$pk_col\`, \`$col_name_escaped\` FROM \`$table_name_escaped\` WHERE \`$col_name_escaped\` LIKE %s&quot;, \t\t\t\t\t\t\t&#039;%&#039; . $wpdb-&gt;esc_like( $old ) . &#039;%&#039;\n\t\t\t\t\t\t))\n$pk_col assigned unsafely at line 2239:\n $pk_col = preg_replace( &#039;/[^a-zA-Z0-9_]/&#039;, &#039;&#039;, $col_def[0] )\n$col_name_escaped assigned unsafely at line 2213:\n $col_name_escaped = preg_replace( &#039;/[^a-zA-Z0-9_]/&#039;, &#039;&#039;, $col_name )\n$table_name_escaped assigned unsafely at line 2205:\n $table_name_escaped = preg_replace( &#039;/[^a-zA-Z0-9_]/&#039;, &#039;&#039;, $table_name )\n$col_def[0] used without escaping.\n$col_name assigned unsafely at line 2212:\n $col_name = $column[0]\n$table_name assigned unsafely at line 2204:\n $table_name = $table[0]\n$column[0] used without escaping. |  |
| 2261 | 29 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 2261 | 29 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 2275 | 29 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 2275 | 29 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |

## `includes/class-cdw-abilities.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 69 | 57 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 70 | 91 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 96 | 54 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 97 | 137 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 105 | 55 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 106 | 128 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 119 | 57 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 120 | 84 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 133 | 59 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 134 | 83 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 147 | 56 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 148 | 116 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 161 | 55 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 162 | 114 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 175 | 55 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 176 | 94 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 193 | 53 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 194 | 122 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 202 | 56 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 203 | 83 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 216 | 55 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 217 | 115 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 230 | 54 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 231 | 113 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 244 | 52 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 245 | 127 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 253 | 54 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 254 | 127 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 271 | 52 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 272 | 134 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 280 | 53 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 281 | 124 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 302 | 53 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 303 | 115 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 316 | 50 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 317 | 132 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 334 | 53 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 335 | 103 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 343 | 52 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 344 | 122 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 357 | 54 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 358 | 113 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 366 | 52 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 367 | 126 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 384 | 58 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 385 | 145 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 397 | 51 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 398 | 156 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 406 | 54 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 407 | 133 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 415 | 55 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 416 | 114 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 429 | 57 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 430 | 67 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 443 | 54 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 444 | 72 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 457 | 56 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 458 | 96 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 471 | 53 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 472 | 116 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 480 | 55 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 481 | 148 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 493 | 55 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 494 | 100 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 502 | 57 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 503 | 122 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 515 | 58 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 516 | 163 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 541 | 65 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 542 | 166 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 550 | 66 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 551 | 122 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 563 | 50 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 564 | 140 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 577 | 53 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 578 | 107 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |

## `includes/class-cdw-widgets.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 48 | 72 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 49 | 74 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 50 | 71 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 51 | 71 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 55 | 72 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 56 | 68 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 57 | 75 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 61 | 77 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 78 | 80 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 82 | 51 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 82 | 135 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 82 | 192 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 86 | 76 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 86 | 218 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 88 | 169 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 164 | 33 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 165 | 38 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 180 | 77 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 182 | 61 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'CDW' but got 'cdw'. | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |

## `.gitattributes`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 0 | 0 | ERROR | hidden_files | No se permiten archivos ocultos. |  |

## `tests/php/stubs/.gitkeep`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 0 | 0 | ERROR | hidden_files | No se permiten archivos ocultos. |  |

## `tests/php/stubs/wp-content/.gitkeep`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 0 | 0 | ERROR | hidden_files | No se permiten archivos ocultos. |  |

## `.phpunit.result.cache`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 0 | 0 | ERROR | hidden_files | No se permiten archivos ocultos. |  |

## `tests/php/stubs/wp-stubs.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 0 | 0 | ERROR | missing_direct_file_access_protection | El archivo PHP debe impedir el acceso directo. Añade una comprobación como: if ( ! defined( “ABSPATH” ) ) exit; | [Documentación](https://developer.wordpress.org/plugins/wordpress-org/common-issues/#direct-file-access) |
| 238 | 14 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$text'. | [Documentación](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 272 | 14 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found 'htmlspecialchars'. | [Documentación](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |

## `includes/class-cdw-loader.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 83 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 83 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 235 | 18 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$button_html'. | [Documentación](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |

## `tests/php/unit/CliServiceHandlersTest.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 891 | 20 | ERROR | WordPress.DateTime.RestrictedFunctions.date_date | date() is affected by runtime timezone changes which can cause date/time to be incorrectly displayed. Use gmdate() instead. |  |

## `CDW.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 0 | 0 | WARNING | textdomain_mismatch | La cabecera "Text Domain" del archivo del plugin no coincide con el slug. Se ha encontrado "cdw", se esperaba "CDW". | [Documentación](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 106 | 3 | ERROR | PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound | load_plugin_textdomain() has been discouraged since WordPress version 4.6. Cuando tu plugin está alojado en WordPress.org, ya no necesitas incluir manualmente esta llamada a la función de traducción en el slug de tu plugin. WordPress cargará automáticamente las traducciones cuando sea necesario. | [Documentación](https://make.wordpress.org/core/2016/07/06/i18n-improvements-in-4-6/) |

## `tests/php/unit/RestApiTest.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 0 | 0 | ERROR | missing_direct_file_access_protection | El archivo PHP debe impedir el acceso directo. Añade una comprobación como: if ( ! defined( “ABSPATH” ) ) exit; | [Documentación](https://developer.wordpress.org/plugins/wordpress-org/common-issues/#direct-file-access) |

## `tests/php/bootstrap.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 0 | 0 | ERROR | missing_direct_file_access_protection | El archivo PHP debe impedir el acceso directo. Añade una comprobación como: if ( ! defined( “ABSPATH” ) ) exit; | [Documentación](https://developer.wordpress.org/plugins/wordpress-org/common-issues/#direct-file-access) |

## `tests/php/stubs/wp-admin/includes/upgrade.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 0 | 0 | ERROR | missing_direct_file_access_protection | El archivo PHP debe impedir el acceso directo. Añade una comprobación como: if ( ! defined( “ABSPATH” ) ) exit; | [Documentación](https://developer.wordpress.org/plugins/wordpress-org/common-issues/#direct-file-access) |

## `tests/php/stubs/wp-admin/includes/plugin.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 0 | 0 | ERROR | missing_direct_file_access_protection | El archivo PHP debe impedir el acceso directo. Añade una comprobación como: if ( ! defined( “ABSPATH” ) ) exit; | [Documentación](https://developer.wordpress.org/plugins/wordpress-org/common-issues/#direct-file-access) |

## `tests/php/stubs/wp-admin/includes/update.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 0 | 0 | ERROR | missing_direct_file_access_protection | El archivo PHP debe impedir el acceso directo. Añade una comprobación como: if ( ! defined( “ABSPATH” ) ) exit; | [Documentación](https://developer.wordpress.org/plugins/wordpress-org/common-issues/#direct-file-access) |

## `tests/php/stubs/wp-admin/includes/file.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 0 | 0 | ERROR | missing_direct_file_access_protection | El archivo PHP debe impedir el acceso directo. Añade una comprobación como: if ( ! defined( “ABSPATH” ) ) exit; | [Documentación](https://developer.wordpress.org/plugins/wordpress-org/common-issues/#direct-file-access) |

## `tests/php/stubs/wp-admin/includes/plugin-install.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 0 | 0 | ERROR | missing_direct_file_access_protection | El archivo PHP debe impedir el acceso directo. Añade una comprobación como: if ( ! defined( “ABSPATH” ) ) exit; | [Documentación](https://developer.wordpress.org/plugins/wordpress-org/common-issues/#direct-file-access) |

## `.distignore`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 0 | 0 | WARNING | hidden_files | No se permiten archivos ocultos. |  |

## `.gitignore`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 0 | 0 | WARNING | hidden_files | No se permiten archivos ocultos. |  |

## `.github`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 0 | 0 | WARNING | github_directory | Se ha detectado el directorio de flujo de trabajo de GitHub «.github». Este directorio no debe incluirse en los plugins en producción. |  |

## `tests/php/integration/SettingsRoundTripTest.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 57 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 57 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 57 | 16 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $table_name used in $wpdb-&gt;query(&quot;DROP TABLE IF EXISTS \`{$table_name}\`&quot;)\n$table_name assigned unsafely at line 55:\n $table_name = $wpdb-&gt;prefix . &#039;cdw_cli_logs&#039; |  |
| 57 | 23 | WARNING | WordPress.DB.DirectDatabaseQuery.SchemaChange | Attempting a database schema change is discouraged. |  |

## `tests/php/integration/CliRoundTripTest.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 60 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 60 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 60 | 23 | WARNING | WordPress.DB.DirectDatabaseQuery.SchemaChange | Attempting a database schema change is discouraged. |  |

## `tests/php/integration/AuditLogTest.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 44 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 44 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 44 | 23 | WARNING | WordPress.DB.DirectDatabaseQuery.SchemaChange | Attempting a database schema change is discouraged. |  |
| 59 | 19 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 59 | 19 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 71 | 22 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 71 | 22 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |

## `includes/functions-uninstall.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 76 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 76 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 87 | 5 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 87 | 5 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 87 | 9 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $table_name used in $wpdb-&gt;query(&quot;DROP TABLE IF EXISTS \`{$table_name}\`&quot;)\n$table_name assigned unsafely at line 85:\n $table_name = $wpdb-&gt;prefix . &#039;cdw_cli_logs&#039; |  |
| 103 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 103 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 108 | 5 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 108 | 5 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |

## `includes/controllers/class-cdw-base-controller.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 167 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 167 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 174 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 174 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
