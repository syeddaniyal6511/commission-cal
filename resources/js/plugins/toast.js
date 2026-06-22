import VueToast from 'vue-toast-notification';
import 'vue-toast-notification/dist/theme-bootstrap.css';

export default function installToast(app) {
    app.use(VueToast);
}
