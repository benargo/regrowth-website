import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// FontAwesome SVG+JS
import '@fortawesome/fontawesome-pro/js/fontawesome.min.js';
import '@fortawesome/fontawesome-pro/js/regular.min.js';
import '@fortawesome/fontawesome-pro/js/brands.min.js';
