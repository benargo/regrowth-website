import React from "react";

import "@fortawesome/fontawesome-pro/js/fontawesome.min.js";
import "@fortawesome/fontawesome-pro/js/solid.min.js";

export default function FontAwesomeSolid({ icon, className = "", ...props }) {
    return <i className={`fas fa-${icon} ${className}`} {...props}></i>;
}
