import Vue from 'vue'
import Vuex from 'vuex'
import axios from 'axios'
import router from "./../../router";
import store from '../../store/index.js'
import { i18n } from "../../plugins/i18n";


Vue.use(Vuex)


const state = {
    isAuthenticated:false,
    Permissions: null,
    allmodules: null,
    user: {},
    loading: false,
    error: null,
    notifs:0,
    Default_Language:'en',
    show_language:1,
};


const getters = {
    getallmodules: state => state.allmodules,
    isAuthenticated: state => state.isAuthenticated,
    currentUser: state => state.user,
    currentUserPermissions: state => state.Permissions,
    loading: state => state.loading,
    notifs_alert: state => state.notifs,
    DefaultLanguage: state => state.Default_Language,
    show_language: state => state.show_language,
    error: state => state.error
};

const mutations = {
    setLoading(state, data) {
        state.loading = data;
        state.error = null;
    },
    setError(state, data) {
        state.error = data;
        state.loggedInUser = null;
        state.loading = false;
    },
    clearError(state) {
        state.error = null;
    },
   
    setPermissions(state, Permissions) {
        state.Permissions = Permissions;
    },

    setallmodules(state, allmodules) {
        state.allmodules = allmodules;
    },

   
    setUser(state, user) {
        state.user = user;
    },


    SetDefaultLanguage(state, Language) {
        i18n.locale = Language;
        store.dispatch("language/setLanguage", Language);
        state.Default_Language = Language;
    },

    Notifs_alert(state, notifs) {
        state.notifs = notifs;
    },

    
    show_language(state, show_language) {
        state.show_language = show_language;
    },


    logout(state) {
        state.user = null;
        state.Permissions = null;
        state.allmodules = null;
        state.loggedInUser = null;
        state.loading = false;
        state.error = null;
    },
};

const actions = {

    async refreshUserPermissions(context) {

        await axios.get("get_user_auth").then((userAuth) => {
            let Permissions = userAuth.data.permissions
            let allmodules = userAuth.data.ModulesEnabled
            let user = userAuth.data.user
            let notifs = userAuth.data.notifs
            let default_language = userAuth.data.user.default_language
            let show_language = userAuth.data.user.show_language

            context.commit('setPermissions', Permissions)
            context.commit('setallmodules', allmodules)
            context.commit('setUser', user)
            context.commit('Notifs_alert', notifs)
            context.commit('show_language', show_language)

            context.commit('SetDefaultLanguage', default_language)
        }).catch(() => {
            context.commit('setPermissions', null)
            context.commit('setallmodules', null)
            context.commit('setallmodules', null)
            context.commit('setUser', null)
            context.commit('Notifs_alert', null)
            context.commit('show_language', null)
            context.commit('SetDefaultLanguage', 'en')
        });
    },

    logout({ commit }) {

        axios({method:'post',  url: '/logout', baseURL: '' })
          .then((userData) => {
            window.location.href='/login';
        })
    },
};

export default {
    state,
    getters,
    actions,
    mutations
};