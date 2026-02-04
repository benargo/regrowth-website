import React from "react";

import "@fortawesome/fontawesome-pro/js/fontawesome.min.js";
import "@fortawesome/fontawesome-pro/js/regular.min.js";

export default function FontAwesomeRegular({ icon, className = "", ...props }) {
    return <i className={`far fa-${icon} ${className}`} {...props}></i>;
}
