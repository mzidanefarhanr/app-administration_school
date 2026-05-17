//import axios
import { notify } from '@kyvg/vue3-notification';
import axios from 'axios';

const apiLogin = axios.create({
    //set default endpoint API
    baseURL: 'http://26.117.240.38:4001',
    withCredentials: true,
    withXSRFToken: true
});

axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response.status === 401) {
            notify({
                type: 'error',
                title: 'Error',
                text: error.response.data.message
            });
        }
        return Promise.reject(error);
    }
);
apiLogin.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
apiLogin.defaults.headers.common['Accept'] = 'application/json';

export default apiLogin;
