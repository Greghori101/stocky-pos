<template>
  <div v-if="Loading">
    <router-view></router-view>


    <customizer v-if="show_language && !isPosPage"></customizer>
  </div>
</template>


<script>
import { mapActions, mapGetters } from "vuex";

export default {
  data() {
    return {
      Loading:false,
    };
  },
  computed: {
    
    ...mapGetters(["getThemeMode","isAuthenticated","show_language"]),
    themeName() {
      return this.getThemeMode.dark ? "dark-theme" : " ";
    },
    rtl() {
      return this.getThemeMode.rtl ? "rtl" : " ";
    },

    isPosPage() {
      return this.$route.path === '/app/pos';
    },
  },

  metaInfo() {
    return {
      // if no subcomponents specify a metaInfo.title, this title will be used
      title: "Stocky",
      // all titles will be injected into this template
      titleTemplate: "%s | Ultimate Inventory With POS",
      bodyAttrs: {
        class: [this.themeName, "text-left"]
      },
      htmlAttrs: {
        dir: this.rtl
      },
      
    };
  },
methods:{
    ...mapActions([
      "refreshUserPermissions",
    ]),
    
},

 beforeMount() {
        this.refreshUserPermissions();
        setTimeout(() => this.Loading= true, 300);
   
  }

};
</script>

