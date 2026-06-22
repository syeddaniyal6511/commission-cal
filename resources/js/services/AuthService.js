import axios from 'axios';

const AuthService = {
    login:           (payload) => axios.post('/api/login', payload).then(r => r.data),
    register:        (payload) => axios.post('/api/register', payload).then(r => r.data),
    logout:          ()        => axios.post('/api/logout').then(r => r.data),
    getProfile:      ()        => axios.get('/api/profile').then(r => r.data),
    updateProfile:   (payload) => axios.put('/api/profile', payload).then(r => r.data),
    updatePassword:  (payload) => axios.put('/api/password', payload).then(r => r.data),
};

export default AuthService;
