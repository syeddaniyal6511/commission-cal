<template>
  <div>
      <nav class="navbar navbar-expand-lg navbar-light grey-bg" >
          <div class="container">
              <router-link to="/" class="navbar-brand">{{title}}</router-link>
              <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar" >
                  <span class="navbar-toggler-icon" />
              </button>
              <div id="navbar" class="collapse navbar-collapse">
                  <ul class="navbar-nav ms-auto">
                      <div class="nav-link text-dark cur-point" data-bs-toggle="collapse" @click="toggleCollapse('Profile')" data-bs-target="#Profile" aria-expanded="false" aria-controls="collapseExample">
                          {{authStore.user.data}} <font-awesome-icon class="ico-c" :icon="angleIcons.Profile" />
                      </div>

                      <div class="collapse" id="Profile">
                                  <ul class="Profile-dropdown">
                                      <li @click="" data-bs-toggle="collapse" data-bs-target="#Profile" aria-expanded="false" aria-controls="collapseExample">
                                          <router-link :to="{ name: 'settings.profile' }" class="dropdown-item ps-3">
                                              <font-awesome-icon icon="user" fixed-width />
                                              {{ ('Profile') }}
                                          </router-link>
                                      </li>
                                        <li>
                                            <div class="dropdown-divider" />
                                            <a href="#" class="dropdown-item ps-3" @click.prevent="logout">
                                                {{ ('Logout') }}
                                                <font-awesome-icon icon="sign-out" fixed-width />
                                            </a>
                                        </li>

                                  </ul>
                      </div>
                  </ul>
              </div>
          </div>
      </nav>
  </div>
</template>

<script>
import './styles.css'
export default {
    inject: ['authStore'],
    name: 'Navbar',
    data (){
        return{
        title: '',
            angleIcons: {
                Profile: "angle-down",
            },
    }
},

    methods: {
        async logout() {
            try {
                await this.LogoutApiCall();
                this.authStore.logout(); // No need to pass data here, assuming the authStore handles logout
                this.$toast.success('User Logout successfully', { position: 'top-right', duration: 3000 });
                // Redirect to the landing page
                this.$router.push('/');
            } catch (e) {
                handleError(e,this.$toast); // Call the defined handleError function here
            }
        },
        async LogoutApiCall() {
            try {
                const response = await this.$axios.post('/api/logout');
                return response.data;
            } catch (e) {
                throw e;
            }
        },
        toggleCollapse(section) {
            if (this.angleIcons[section] === "angle-down") {
                this.angleIcons[section] = "angle-up";
            } else {
                this.angleIcons[section] = "angle-down";
            }
        },
    }
};
</script>

<style scoped>

.container {
    max-width: 1100px;
}

</style>
