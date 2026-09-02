import { createSlice } from '@reduxjs/toolkit';
import { AUTH_TOKEN_KEY } from '../../constants/apiConfig.js';

const token = localStorage.getItem(AUTH_TOKEN_KEY);

const initialState = {
  token,
  profile: null,
  roles: [],
  permissions: [],
  isAuthenticated: Boolean(token),
  loading: false,
};

const authSlice = createSlice({
  name: 'auth',
  initialState,
  reducers: {
    setCredentials(state, action) {
      const { token, profile, roles = [], permissions = [] } = action.payload;
      state.token = token;
      state.profile = profile;
      state.roles = roles;
      state.permissions = permissions;
      state.isAuthenticated = Boolean(token);
      state.loading = false;

      if (token) {
        localStorage.setItem(AUTH_TOKEN_KEY, token);
      }
    },
    clearCredentials(state) {
      state.token = null;
      state.profile = null;
      state.roles = [];
      state.permissions = [];
      state.isAuthenticated = false;
      state.loading = false;
      localStorage.removeItem(AUTH_TOKEN_KEY);
    },
    setAuthLoading(state, action) {
      state.loading = action.payload;
    },
  },
});

export const { setCredentials, clearCredentials, setAuthLoading } = authSlice.actions;
export default authSlice.reducer;
