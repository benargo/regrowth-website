import React from "react";

import "@fortawesome/fontawesome-pro/js/fontawesome.min.js";
import "@fortawesome/fontawesome-pro/js/light.min.js";

export default function FontAwesomeLight({ icon, className = "", ...props }) {
    return <i className={`fal fa-${icon} ${className}`} {...props}></i>;
}
