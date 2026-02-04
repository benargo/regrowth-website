import React from "react";

import "@fortawesome/fontawesome-pro/js/fontawesome.min.js";
import "@fortawesome/fontawesome-pro/js/brands.min.js";

export default function FontAwesomeBrands({ icon, className = "", ...props }) {
    return <i className={`fab fa-${icon} ${className}`} {...props}></i>;
}
