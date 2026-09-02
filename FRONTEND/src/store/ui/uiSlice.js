import { createSlice } from '@reduxjs/toolkit';

const initialState = {
  sidebarOpen: false,
  sidebarCollapsed: false,
  globalLoading: false,
  toast: null,
};

const uiSlice = createSlice({
  name: 'ui',
  initialState,
  reducers: {
    toggleSidebar(state) {
      state.sidebarOpen = !state.sidebarOpen;
    },
    setSidebarOpen(state, action) {
      state.sidebarOpen = action.payload;
    },
    toggleSidebarCollapsed(state) {
      state.sidebarCollapsed = !state.sidebarCollapsed;
    },
    setSidebarCollapsed(state, action) {
      state.sidebarCollapsed = action.payload;
    },
    setGlobalLoading(state, action) {
      state.globalLoading = action.payload;
    },
    setToast(state, action) {
      state.toast = action.payload;
    },
    clearToast(state) {
      state.toast = null;
    },
  },
});

export const {
  toggleSidebar,
  setSidebarOpen,
  toggleSidebarCollapsed,
  setSidebarCollapsed,
  setGlobalLoading,
  setToast,
  clearToast,
} = uiSlice.actions;

export default uiSlice.reducer;
