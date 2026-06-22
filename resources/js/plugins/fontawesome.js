import { library, config } from '@fortawesome/fontawesome-svg-core';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import '@fortawesome/fontawesome-svg-core/styles.css'
import { faStar as farStar} from '@fortawesome/free-regular-svg-icons'; // Import regular icons



import {
    faUser,faCalculator, faLock, faSignOutAlt, faCog, faTrash, faEye, faPen, faDownload, faPlus, faClock, faTimesCircle, faCheckCircle, faCheck, faSearch, faCartShopping, faSignIn, faSignOut, faArrowLeft, faAngleUp, faAngleDown, faTruck, faStar, faChevronLeft, faChevronRight, faBars, faHome, faCubes, faTags, faList, faShoppingCart, faImage, faRankingStar, faShoppingBasket, faUsers, faFileInvoice, faTicketAlt, faFlag, faMoneyBillWave, faMapMarkerAlt, faMoneyCheck, faCogs, faLocationPin, faFileText, faPhoneAlt, faCheckDouble, faCloudUpload, faFont, faPencilSquare, faArrowCircleLeft,
    faFileContract, faMagnifyingGlass, faCircleNotch
} from '@fortawesome/free-solid-svg-icons';
import { faGithub, faFacebook, faInstagram, faTwitter, faGoogle, faLinkedin } from '@fortawesome/free-brands-svg-icons';

config.autoAddCss = false;

library.add(
    faUser,faCalculator, faLock, faSignOutAlt, faCog, faGithub, faTrash, faEye, faPen, faDownload, faPlus, faClock, faTimesCircle, faCheckCircle, faCheck, faSearch, faCartShopping, faSignIn, faSignOut, faArrowLeft,  faAngleUp, faAngleDown, faTruck, faStar, farStar, faChevronLeft, faChevronRight, faBars, faFacebook, faInstagram, faTwitter, faGoogle, faLinkedin, faHome, faCubes, faTags, faList, faShoppingCart, faImage, faRankingStar, faShoppingBasket, faUsers, faFileInvoice, faTicketAlt, faFlag, faMoneyBillWave, faMapMarkerAlt, faMoneyCheck, faCogs, faLocationPin, faFileText, faPhoneAlt, faCheckDouble, faCloudUpload, faFont, faPencilSquare, faArrowCircleLeft,
    faFileContract, faMagnifyingGlass, faCircleNotch
);

export default {
    install(app) {
        // Register FontAwesomeIcon component globally
        app.component('FontAwesomeIcon', FontAwesomeIcon);
    }
};
