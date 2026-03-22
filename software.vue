<template>
    <div>
        <template>
            <v-container id="software-download">
                <v-card class="pa-6 elevation-0" elevation="0">
                    <div class=" section-title">
                        <h1 class="title"><span class="bold">Update the software for your</span><br>CarPlay / Android
                            Auto MMI</h1>
                    </div>

                    <v-form ref="form" @submit.prevent="checkSoftware" :class="{ loading: softwareDownloadFormSubmit }"
                        class="mt-10">
                        <v-row dense>
                            <v-col cols="12" sm="4" class="mx-auto py-0">
                                <v-text-field dense label="Software Version" v-model="version"
                                    placeholder="Your software version*" :rules="[rules.required]"
                                    outlined></v-text-field>
                            </v-col>
                        </v-row>
                        <v-row dense>
                            <v-col cols="12" sm="4" class="mx-auto py-0">
                                <v-text-field dense class="disabled" label="MCU Verision Not Required"
                                    v-model="mcuVersion" placeholder="Not Required" disabled outlined></v-text-field>
                            </v-col>
                        </v-row>
                        <v-row dense>
                            <v-col cols="12" sm="4" class="mx-auto py-0">
                                <v-text-field dense label="HW Version" outlined v-model="hwVersion"
                                    placeholder="Your HW version*" :rules="[rules.required]"></v-text-field>
                            </v-col>
                        </v-row>
                        <div class="d-flex align-center justify-center mt-3">
                            <v-btn color="primary" type="submit" class="">Check</v-btn>

                            <v-tooltip bottom max-width="250px">
                                <template v-slot:activator="{ on, attrs }">
                                    <v-icon v-bind="attrs" v-on="on" class="ml-2">mdi-information</v-icon>
                                </template>
                                <span style="width: 18px;">Enter your current software version to check if there is an
                                    update available. You can find
                                    your current software in MMI settings > System > System/firmware version.</span>
                            </v-tooltip>
                        </div>


                        <v-alert v-if="versionExist" class="mt-4">
                            <p style="color: #389e0d !important;" class="mb-0">{{ msg }} <a
                                    style="text-transform: uppercase;font-size: 16px !important;text-decoration: underline;"
                                    v-if="st" :href="st" target="_blank" class="download-link">ST Version</a>
                                <a style="text-transform: uppercase;font-size: 16px !important;text-decoration: underline;"
                                    v-if="gd" :href="gd" target="_blank" class="download-link">GD Version</a>
                            </p>
                        </v-alert>
                        <v-alert v-else class="mt-4">
                            <p v-if="msg" style="color: red !important;" class="mb-0">{{ msg }}
                            </p>
                        </v-alert>
                        <p class="mt-3">
                            <a id="softwareModal" style="text-decoration: underline" @click="showModal = true">What is my
                                current software?</a>
                        </p>
                        <v-alert color="" class="mt-4">
                            <strong class="black1--text">Warning!!!</strong><br />
                            <p class="black1--text">
                                Entering an incorrect software number and uploading it to another version of MMI will
                                brick
                                the MMI.<br />
                                In most cases, this is a reversible process, but impossible to perform without the
                                assistance of our
                                support.<br />
                                BimmerTech is not responsible for errors in software uploading by customers due to
                                uploading
                                the incorrect
                                version.<br />
                                After this step, replacement under warranty cannot be considered.
                            </p>
                        </v-alert>
                    </v-form>
                </v-card>

                <v-card class="mt-4 elevation-0 pa-6" elevation="0">
                    <v-card-text>
                        <v-btn color="primary" href="https://newshop.testshop1.bimmer-tech.net/" target="_blank">Go back to the
                            shop</v-btn>
                        <p class="mt-3 text-caption">
                            BMW and MINI are registered trademarks of Bayerische Motoren Werke AG. BimmerTech is not
                            affiliated with BMW
                            AG in any way, and is not authorized by BMW AG to act as an official distributor or
                            representative.
                        </p>
                    </v-card-text>
                </v-card>
            </v-container>
        </template>
        <v-dialog v-model="showModal" max-width="70%" @click:outside="showModal = false">
    <v-card>
        <v-card-actions class="justify-end" style="background: black !important;">
            <v-btn color="white" icon @click="showModal = false" class="dialog-close-btn">
                <v-icon>mdi-close-thick</v-icon>
            </v-btn>
        </v-card-actions>
        <v-card-text class="pa-0">
            <img width="100%" height="100%" src="../assets/mmi_software_version.jpg" />
        </v-card-text>
    </v-card>
</v-dialog>


    </div>
</template>


<script>
import axios from 'axios';

export default {
    layout: "empty",
    data() {
        return {
            rules: {
                required: (value) => !!value || "This field is required",
            },
            showModal: false,
            version: '',
            mcuVersion: '',
            hwVersion: '',
            showForm: true,
            embadded: false,
            softwareDownloadFormSubmit: false,
            versionExist: false,
            msg: '',
            st: '',
            gd: '',
        };
    },

    methods: {
        async checkSoftware() {
            if (!this.$refs.form.validate()) return; // Ensure form validation

            // Reset previous states
            this.versionExist = false;
            this.msg = '';
            this.link = ''; // Ensure old links are cleared
            this.softwareDownloadFormSubmit = true;

            try {
                const response = await axios.post(
                    'https://www.bimmer-tech.net/api2/carplay/software/version',
                    {
                        version: this.version,
                        mcuVersion: this.mcuVersion,
                        hwVersion: this.hwVersion
                    }
                );

                if (response.status === 200 && response.data) {
                    this.versionExist = response.data.versionExist || false;
                    this.st = response.data.st
                    this.gd = response.data.gd
                    this.msg = response.data.msg || 'No message received.';

                    if (this.versionExist) {
                        this.link = response.data.link || ''; // Ensure valid link assignment
                    } else {
                        this.link = ''; // Reset if version doesn't exist
                    }
                } else {
                    this.msg = 'Something went wrong. Please try again.';
                }
            } catch (error) {
                console.error(error);
                this.msg = 'Failed to fetch software details. Please try again.';
            } finally {
                this.softwareDownloadFormSubmit = false;
            }
        },

        openModal() {
            this.showModal = true
        }
    }
};
</script>
<style scoped>
#software-download {
    font-family: 'Rubik', sans-serif;
    color: #212121;
    text-align: center;
}

.container,
#software-download.container {
    width: 100%;
    margin: 0px auto;
}

.section-compatibility {
    padding: 0px 16px;
}

.section-top {
    padding-top: 15%;
}

.section-title {
    box-shadow: inset 2px 3px 5px #00000040, 2px 3px 5px #00000040;
    border: 1px solid #FFFFFF;
    border-radius: 20px !important;
    padding: 24px;
    max-width: 590px;
    margin: 0px auto;
    background: #FFFFFF;
}

.section-title .title {
    font-size: 42px !important;
    line-height: 48px;
    letter-spacing: -0.48px;
    color: #212121;
    font-weight: 300;
    text-align: center;
    margin: 0px;
    padding: 0px;
}

.section-title .title .bold {
    font-size: 36px;
    letter-spacing: -0.36px;
    font-weight: 700;
}

.section-goto-shop {
    padding-top: 16px;
}

.section-goto-shop .shop-link {
    color: #1CA3C2;
    font-size: 16px;
    line-height: 36px;
    letter-spacing: -0.16px;
    text-decoration: none;
    padding: 0px 40px 16px;
    border-bottom: 1px solid #E0E0E0;
}

.section-goto-shop .goto-shop-msg {
    color: #212121;
    font-size: 12px;
    line-height: 20px;
    letter-spacing: -0.12px;
    padding-top: 16px;
}

.vin-form {
    min-height: 50px;
    padding: 40px 0px 0px;
    margin-bottom: 20%;
}

.vin-form input,
.vin-form button {
    font-size: 14px;
    line-height: 20px;
    border-radius: 4px;
}

.vin-form input {
    box-shadow: inset 0px 3px 6px #0000005E;
    border: 1px solid #E0E0E0;
    padding: 9px 15px;
    margin-right: 5px;
}

.vin-form button {
    background: transparent linear-gradient(180deg, #1CA3C2 0%, #246574 100%) 0% 0% no-repeat padding-box;
    color: #FFFFFF;
    border: none;
    padding: 10px 30px;
    cursor: pointer;
}

.vin-form button:hover {
    background: transparent linear-gradient(190deg, #1CA3C2 0%, #246574 100%) 0% 0% no-repeat padding-box;
}

.tooltip {
    font-size: 14px;
    color: #aaaaaa;
    width: 18px;
    height: 18px;
    position: relative;
    display: inline-block;
    border-radius: 50%;
    border: 1px solid #aaaaaa;
    margin-left: 5px;
    cursor: help;
    line-height: 18px;
    z-index: 1;
}

.tooltip:before {
    content: attr(data-text);
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    right: 100%;
    margin-right: 10px;
    width: 200px;
    padding: 10px;
    border-radius: 10px;
    background: #000;
    color: #fff;
    text-align: center;
    display: none;
}

.tooltip:after {
    content: "";
    position: absolute;
    right: 100%;
    margin-right: -10px;
    top: 50%;
    transform: translateY(-50%);
    border: 10px solid #000;
    border-color: transparent transparent transparent black;

    display: none;
}

.tooltip:hover:before,
.tooltip:hover:after {
    display: block;
}

.loading {
    position: relative;
    cursor: default;
    pointer-events: none;
}

.loading:before {
    position: absolute;
    content: "";
    top: 0;
    left: 0;
    background: rgba(255, 255, 255, 0.8);
    width: 100%;
    height: 100%;
    z-index: 100;
}

.loading:after {
    position: absolute;
    content: "";
    top: 50%;
    left: 50%;
    margin: -1.5em 0 0 -1.5em;
    width: 3em;
    height: 3em;
    -webkit-animation: form-spin 0.6s linear;
    animation: form-spin 0.6s linear;
    -webkit-animation-iteration-count: infinite;
    animation-iteration-count: infinite;
    border-radius: 500rem;
    border-color: #757575 rgba(0, 0, 0, 0.1) rgba(0, 0, 0, 0.1);
    border-style: solid;
    border-width: 0.2em;
    -webkit-box-shadow: 0 0 0 1px transparent;
    box-shadow: 0 0 0 1px transparent;
    visibility: visible;
    z-index: 101;
}

@-webkit-keyframes form-spin {
    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }
}

@keyframes form-spin {
    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }
}

.download-link {
    color: #389e0d;
}

.compatiable-msg {
    font-size: 20px;
    line-height: 28px;
    letter-spacing: -0.4px;
    color: #389e0d;
    /*            cursor:  pointer;*/
    font-weight: 300;
    margin-bottom: 0px;
    display: block;
}

.compatiable-msg.not-compatiable {
    color: #F5350B;
}

.flipInX {
    -webkit-backface-visibility: visible !important;
    backface-visibility: visible !important;
    -webkit-animation-name: flipInX;
    animation-name: flipInX;
    -webkit-animation-duration: 1s;
    animation-duration: 1s;
    -webkit-animation-fill-mode: both;
    animation-fill-mode: both;
}

@-webkit-keyframes flipInX {
    0% {
        -webkit-transform: perspective(400px) rotate3d(1, 0, 0, 90deg);
        transform: perspective(400px) rotate3d(1, 0, 0, 90deg);
        -webkit-transition-timing-function: ease-in;
        transition-timing-function: ease-in;
        opacity: 0;
    }

    40% {
        -webkit-transform: perspective(400px) rotate3d(1, 0, 0, -20deg);
        transform: perspective(400px) rotate3d(1, 0, 0, -20deg);
        -webkit-transition-timing-function: ease-in;
        transition-timing-function: ease-in;
    }

    60% {
        -webkit-transform: perspective(400px) rotate3d(1, 0, 0, 10deg);
        transform: perspective(400px) rotate3d(1, 0, 0, 10deg);
        opacity: 1;
    }

    80% {
        -webkit-transform: perspective(400px) rotate3d(1, 0, 0, -5deg);
        transform: perspective(400px) rotate3d(1, 0, 0, -5deg);
    }

    100% {
        -webkit-transform: perspective(400px);
        transform: perspective(400px);
    }
}

@keyframes flipInX {
    0% {
        -webkit-transform: perspective(400px) rotate3d(1, 0, 0, 90deg);
        transform: perspective(400px) rotate3d(1, 0, 0, 90deg);
        -webkit-transition-timing-function: ease-in;
        transition-timing-function: ease-in;
        opacity: 0;
    }

    40% {
        -webkit-transform: perspective(400px) rotate3d(1, 0, 0, -20deg);
        transform: perspective(400px) rotate3d(1, 0, 0, -20deg);
        -webkit-transition-timing-function: ease-in;
        transition-timing-function: ease-in;
    }

    60% {
        -webkit-transform: perspective(400px) rotate3d(1, 0, 0, 10deg);
        transform: perspective(400px) rotate3d(1, 0, 0, 10deg);
        opacity: 1;
    }

    80% {
        -webkit-transform: perspective(400px) rotate3d(1, 0, 0, -5deg);
        transform: perspective(400px) rotate3d(1, 0, 0, -5deg);
    }

    100% {
        -webkit-transform: perspective(400px);
        transform: perspective(400px);
    }
}

#zoom-continer {
    display: none;
}

::v-deep .disabled .v-input__control .v-input__slot {
    background-color: #f2f2f2 !important;
    /* Set text color to red */
}

@media only screen and (max-width: 767px) {
    .section-title {
        padding: 14px;
    }

    .section-title .title {
        font-size: 35px;
        line-height: 40px;
        letter-spacing: -0.35px;
    }

    .section-title .title .bold {
        font-size: 27px;
        line-height: 40px;
        letter-spacing: -0.27px;
    }
}

@media only screen and (min-width: 576px) {
    .container {
        max-width: 540px;
    }
}

@media only screen and (min-width: 768px) {
    .container {
        max-width: 720px;
    }
}

@media only screen and (min-width: 992px) {
    .container {
        max-width: 960px;
    }
}

@media only screen and (min-width: 1200px) {
    .container {
        max-width: 1140px;
    }
}
</style>
