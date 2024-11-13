#!/bin/bash
echo "Judge Me"
current_dir="$PWD"
pro_plugin_name="wp-loyalty-judge-me"
pack_pro_folder=$current_dir"/../compressed_pack"
plugin_pro_compress_folder=$pack_pro_folder"/"$pro_plugin_name
composer_run() {
  # shellcheck disable=SC2164
  cd "$current_dir"
  composer install --no-dev
  composer update --no-dev
  cd ..
  echo "Compress Done"
  cd "$current_dir"
}
update_ini_file() {
  cd $current_dir
  wp i18n make-pot . "i18n/languages/$pro_plugin_name.pot" --slug="wp-loyalty-judge-me" --domain="wp-loyalty-judge-me" --include=$pro_plugin_name".php",/App/ --headers='{"Last-Translator":"WPLoyalty","Language-Team":"WPLoyalty"}' --allow-root
  cd $current_dir
  echo "Update ini done"
}
copy_pro_folder() {
  if [ -d "$pack_pro_folder" ]; then
    rm -r "$pack_pro_folder"
  fi
  mkdir "$pack_pro_folder"
  mkdir "$plugin_pro_compress_folder"
  move_dir=("App" "Assets" "i18n" "vendor" "composer.json" $pro_plugin_name".php")
  # shellcheck disable=SC2068
  for dir in ${move_dir[@]}; do
    cp -r "$current_dir/$dir" "$plugin_pro_compress_folder/$dir"
  done
}
zip_pro_folder() {
  cd "$pack_pro_folder"
  rm "$pro_plugin_name".zip
  zip -r "$pro_plugin_name".zip $pro_plugin_name -q
  zip -d "$pro_plugin_name".zip __MACOSX/\*
  zip -d "$pro_plugin_name".zip \*/.DS_Store
}
echo "Composer Run:"
composer_run
echo "Update ini"
update_ini_file
echo "Copy Folder:"
copy_pro_folder
echo "Zip Folder:"
zip_pro_folder
echo "End"
