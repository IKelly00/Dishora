import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import html from '@rollup/plugin-html';
import { glob } from 'glob';

/**
 * Get Files from a directory
 * @param {string} query
 * @returns array
 */
function GetFilesArray(query) {
  return glob.sync(query);
}

/**
 * Js Files
 */
// Page JS Files
const pageJsFiles = GetFilesArray('resources/assets/js/*.js');

// Start-Selling specific JS
const startSellingJs = ['resources/js/start-selling-imports.js'];

// Processing Vendor JS Files
const vendorJsFiles = GetFilesArray('resources/assets/vendor/js/*.js');

// Processing Libs JS Files
const LibsJsFiles = GetFilesArray('resources/assets/vendor/libs/**/*.js');

/**
 * Scss & CSS Files
 */
// Processing Core, Themes & Pages Scss Files
const CoreScssFiles = GetFilesArray('resources/assets/vendor/scss/**/!(_)*.scss');

// Processing Libs Scss & Css Files
const LibsScssFiles = GetFilesArray('resources/assets/vendor/libs/**/!(_)*.scss');
const LibsCssFiles = GetFilesArray('resources/assets/vendor/libs/**/*.css');

// Processing Fonts Scss Files
const FontsScssFiles = GetFilesArray('resources/assets/vendor/fonts/**/!(_)*.scss');

export default defineConfig({
  base: '',
  build: {
    outDir: 'public/build'
  },
  plugins: [
    laravel({
      input: [
        'resources/css/app.css',
        'resources/assets/css/demo.css',
        'resources/js/app.js',
        'resources/js/philippine-addresses.js',
        ...pageJsFiles,
        ...startSellingJs, // <-- Added your Blade-specific JS
        ...vendorJsFiles,
        ...LibsJsFiles,
        ...CoreScssFiles,
        ...LibsScssFiles,
        ...LibsCssFiles,
        ...FontsScssFiles
      ],
      refresh: true
    }),
    html()
  ]
  // server: {
  //   host: '127.0.0.1',
  //   port: 5174
  // }
  // Add this server block
  // server: {
  //   host: true // This is the magic line
  //   // You can also use '0.0.0.0'
  //   // host: '0.0.0.0',
  // }
  // server: {
  //   // KEY #1: Makes the server accessible to ngrok
  //   host: '0.0.0.0'

  //   // // KEY #2: Tells the browser the correct public URL to use
  //   // hmr: {
  //   //   host: 'marcy-ophthalmoscopic-wobbly.ngrok-free.dev',
  //   //   protocol: 'wss'
  //   // }
  // }
});
