// axios-plugin.js
import axios from 'axios';

import {useAuthStore} from "../store/auth";
const AxiosPlugin = {
    install(app) {
        axios.interceptors.request.use(config => {
            const token = useAuthStore().getToken();
            if (token) {
                config.headers['Authorization'] = `Bearer ${token}`;
            }
            return config;
        });

        app.config.globalProperties.$axios = axios;
    }
};

export default AxiosPlugin;
