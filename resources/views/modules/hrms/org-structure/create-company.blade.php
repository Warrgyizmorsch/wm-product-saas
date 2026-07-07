@extends('layouts.duralux')

@section('title', 'ORG STRUCTURE | SaaS ERP')
@section('page-title', 'Create Legal Entities')
@section('breadcrumb', 'HRMS / Org Structure / Legal Entities / Create')

@section('page-actions')
    <x-ui.button href="{{ route('crm.customers.index') }}" variant="light" icon="feather-arrow-left">
        Back to Customers
    </x-ui.button>
@endsection

@section('content')
    <div class="row">
                    <div class="col-lg-12">
                        <div class="card border-top-0">
                            <div>
                                <div class="card-body personal-info">
                                    <form action="{{ route('hrms.company.store') }}" method="POST" enctype="multipart/form-data">
                                    @csrf
                                        <div class="mb-4 d-flex align-items-center justify-content-between">
                                            <h5 class="fw-bold mb-0 me-4">
                                                <span class="d-block mb-2">Company Information:</span>
                                                <!-- <span class="fs-12 fw-normal text-muted text-truncate-1-line">Following information is publicly displayed, be careful! </span> -->
                                            </h5>
                                            <x-ui.button type="submit" variant="light-brand" size="lg">Add New</x-ui.button>
                                        </div>
                                         <!-- Logo Section -->
                                         <div class="mb-4">
                                             <label class="form-label fw-semibold">Logo: </label>
                                             <div class="mb-4 mb-md-0 d-flex gap-4 your-brand">
                                                 <div class="wd-100 ht-100 position-relative overflow-hidden border border-gray-2 rounded">
                                                     <img src="{{ asset('assets/images/avatar/1.png') }}" class="upload-pic img-fluid rounded h-100 w-100" alt="">
                                                     <div class="position-absolute start-50 top-50 end-0 translate-middle h-100 w-100 hstack align-items-center justify-content-center c-pointer upload-button">
                                                         <i class="feather feather-camera" aria-hidden="true"></i>
                                                     </div>
                                                     <input class="file-upload" type="file" name="logo" accept="image/*" style="display: none;">
                                                 </div>
                                                 <div class="d-flex flex-column gap-1">
                                                     <div class="fs-11 text-gray-500 mt-2"># Upload your profile</div>
                                                     <div class="fs-11 text-gray-500"># Avatar size 150x150</div>
                                                     <div class="fs-11 text-gray-500"># Max upload size 2mb</div>
                                                     <div class="fs-11 text-gray-500"># Allowed file types: png, jpg, jpeg</div>
                                                 </div>
                                             </div>
                                         </div>
                                        <!-- Row 1: Company Name & Legal Name -->
                                        <div class="row mb-4">
                                            <div class="col-md-6 mb-3 mb-md-0">
                                                <x-ui.odoo-form-ui type="input" label="Company Name" name="company_name" id="companyNameInput" :required="true" placeholder="Company Name" :errorText="$errors->first('company_name')" />
                                            </div>
                                            <div class="col-md-6">
                                                <x-ui.odoo-form-ui type="input" label="Legal Name" name="legal_name" id="legalNameInput" :required="true" placeholder="Legal Name" :errorText="$errors->first('legal_name')" />
                                            </div>
                                        </div>

                                        <!-- Row 2: GST Number & PAN Number -->
                                        <div class="row mb-4">
                                            <div class="col-md-6 mb-3 mb-md-0">
                                                <x-ui.odoo-form-ui type="input" label="GST Number" name="gst_number" id="gstNumberInput" placeholder="GST" :errorText="$errors->first('gst_number')" />
                                            </div>
                                            <div class="col-md-6">
                                                <x-ui.odoo-form-ui type="input" label="PAN Number" name="pan_number" id="panNumberInput" placeholder="PAN" :errorText="$errors->first('pan_number')" />
                                            </div>
                                        </div>

                                        <!-- Row 3: CIN Number & Registration Number -->
                                        <div class="row mb-4">
                                            <div class="col-md-6 mb-3 mb-md-0">
                                                <x-ui.odoo-form-ui type="input" label="CIN Number" name="cin_number" id="cinNumberInput" placeholder="CIN" :errorText="$errors->first('cin_number')" />
                                            </div>
                                            <div class="col-md-6">
                                                <x-ui.odoo-form-ui type="input" label="Registration Number" name="registration_number" id="registrationNumberInput" placeholder="Registration Number" :errorText="$errors->first('registration_number')" />
                                            </div>
                                        </div>

                                        <!-- Row 4: Email & Phone -->
                                        <div class="row mb-4">
                                            <div class="col-md-6 mb-3 mb-md-0">
                                                <x-ui.odoo-form-ui type="input" label="Email" name="email" id="emailInput" placeholder="Email" :errorText="$errors->first('email')" />
                                            </div>
                                            <div class="col-md-6">
                                                <x-ui.odoo-form-ui type="input" label="Phone" name="phone" id="phoneInput" placeholder="Phone" :errorText="$errors->first('phone')" />
                                            </div>
                                        </div>

                                        <!-- Row 5: Website & Postal Code -->
                                        <div class="row mb-4">
                                            <div class="col-md-6 mb-3 mb-md-0">
                                                <x-ui.odoo-form-ui type="input" label="Website" name="website" id="websiteInput" placeholder="Website" :errorText="$errors->first('website')" />
                                            </div>
                                            <div class="col-md-6">
                                                <x-ui.odoo-form-ui type="input" label="Postal Code" name="postal_code" id="postalCodeInput" placeholder="Postal Code" :errorText="$errors->first('postal_code')" />
                                            </div>
                                        </div>

                                        <!-- Row 6: Address (Full Width) -->
                                        <div class="mb-4">
                                            <x-ui.odoo-form-ui type="textarea" label="Address" name="address" id="addressInput" rows="3" placeholder="Address" :errorText="$errors->first('address')" />
                                        </div>

                                        <!-- Row 7: City & State -->
                                        <div class="row mb-4">
                                            <div class="col-md-6 mb-3 mb-md-0">
                                                <x-ui.odoo-form-ui type="input" label="City" name="city" id="cityInput" placeholder="City" :errorText="$errors->first('city')" />
                                            </div>
                                            <div class="col-md-6">
                                                <x-ui.odoo-form-ui type="input" label="State" name="state" id="stateInput" placeholder="State" :errorText="$errors->first('state')" />
                                            </div>
                                        </div>
                                        <div class="row mb-4">
                                            <div class="col-md-6 mb-3 mb-md-0">
                                                <x-ui.odoo-form-ui type="select" label="Country" name="country" select2-selector="country" :errorText="$errors->first('country')">
                                                    <option data-country="af">Afghanistan</option>
                                                    <option data-country="ax">Åland Islands</option>
                                                    <option data-country="al">Albania</option>
                                                    <option data-country="dz">Algeria</option>
                                                    <option data-country="as">American Samoa</option>
                                                    <option data-country="ad">Andorra</option>
                                                    <option data-country="ao">Angola</option>
                                                    <option data-country="ai">Anguilla</option>
                                                    <option data-country="aq">Antarctica</option>
                                                    <option data-country="ag">Antigua & Barbuda</option>
                                                    <option data-country="ar">Argentina</option>
                                                    <option data-country="am">Armenia</option>
                                                    <option data-country="aw">Aruba</option>
                                                    <option data-country="au">Australia</option>
                                                    <option data-country="at">Austria</option>
                                                    <option data-country="az">Azerbaijan</option>
                                                    <option data-country="bs">Bahamas</option>
                                                    <option data-country="bh">Bahrain</option>
                                                    <option data-country="bd">Bangladesh</option>
                                                    <option data-country="bb">Barbados</option>
                                                    <option data-country="by">Belarus</option>
                                                    <option data-country="be">Belgium</option>
                                                    <option data-country="bz">Belize</option>
                                                    <option data-country="bj">Benin</option>
                                                    <option data-country="bm">Bermuda</option>
                                                    <option data-country="bt">Bhutan</option>
                                                    <option data-country="bo">Bolivia</option>
                                                    <option data-country="bq">Caribbean Netherlands</option>
                                                    <option data-country="ba">Bosnia & Herzegovina</option>
                                                    <option data-country="bw">Botswana</option>
                                                    <option data-country="bv">Bouvet Island</option>
                                                    <option data-country="br">Brazil</option>
                                                    <option data-country="io">British Indian Ocean Territory</option>
                                                    <option data-country="bn">Brunei</option>
                                                    <option data-country="bg">Bulgaria</option>
                                                    <option data-country="bf">Burkina Faso</option>
                                                    <option data-country="bi">Burundi</option>
                                                    <option data-country="kh">Cambodia</option>
                                                    <option data-country="cm">Cameroon</option>
                                                    <option data-country="ca">Canada</option>
                                                    <option data-country="cv">Cape Verde</option>
                                                    <option data-country="ky">Cayman Islands</option>
                                                    <option data-country="cf">Central African Republic</option>
                                                    <option data-country="td">Chad</option>
                                                    <option data-country="cl">Chile</option>
                                                    <option data-country="cn">China</option>
                                                    <option data-country="cx">Christmas Island</option>
                                                    <option data-country="cc">Cocos (Keeling) Islands</option>
                                                    <option data-country="co">Colombia</option>
                                                    <option data-country="km">Comoros</option>
                                                    <option data-country="cg">Congo - Brazzaville</option>
                                                    <option data-country="cd">Congo - Kinshasa</option>
                                                    <option data-country="ck">Cook Islands</option>
                                                    <option data-country="cr">Costa Rica</option>
                                                    <option data-country="ci">Côte d'Ivoire</option>
                                                    <option data-country="hr">Croatia</option>
                                                    <option data-country="cu">Cuba</option>
                                                    <option data-country="cu">Curaçao</option>
                                                    <option data-country="cy">Cyprus</option>
                                                    <option data-country="cz">Czechia</option>
                                                    <option data-country="dk">Denmark</option>
                                                    <option data-country="dj">Djibouti</option>
                                                    <option data-country="dm">Dominica</option>
                                                    <option data-country="do">Dominican Republic</option>
                                                    <option data-country="ec">Ecuador</option>
                                                    <option data-country="eg">Egypt</option>
                                                    <option data-country="sv">El Salvador</option>
                                                    <option data-country="gq">Equatorial Guinea</option>
                                                    <option data-country="er">Eritrea</option>
                                                    <option data-country="ee">Estonia</option>
                                                    <option data-country="et">Ethiopia</option>
                                                    <option data-country="fk">Falkland Islands (Islas Malvinas)</option>
                                                    <option data-country="fo">Faroe Islands</option>
                                                    <option data-country="fj">Fiji</option>
                                                    <option data-country="fi">Finland</option>
                                                    <option data-country="fr">France</option>
                                                    <option data-country="gf">French Guiana</option>
                                                    <option data-country="pf">French Polynesia</option>
                                                    <option data-country="tf">French Southern Territories</option>
                                                    <option data-country="ga">Gabon</option>
                                                    <option data-country="gm">Gambia</option>
                                                    <option data-country="ge">Georgia</option>
                                                    <option data-country="de">Germany</option>
                                                    <option data-country="gh">Ghana</option>
                                                    <option data-country="gi">Gibraltar</option>
                                                    <option data-country="gr">Greece</option>
                                                    <option data-country="gl">Greenland</option>
                                                    <option data-country="gd">Grenada</option>
                                                    <option data-country="gp">Guadeloupe</option>
                                                    <option data-country="gu">Guam</option>
                                                    <option data-country="gt">Guatemala</option>
                                                    <option data-country="gg">Guernsey</option>
                                                    <option data-country="gn">Guinea</option>
                                                    <option data-country="gw">Guinea-Bissau</option>
                                                    <option data-country="gy">Guyana</option>
                                                    <option data-country="ht">Haiti</option>
                                                    <option data-country="hm">Heard & McDonald Islands</option>
                                                    <option data-country="va">Vatican City</option>
                                                    <option data-country="hn">Honduras</option>
                                                    <option data-country="hk">Hong Kong</option>
                                                    <option data-country="hu">Hungary</option>
                                                    <option data-country="is">Iceland</option>
                                                    <option data-country="in">India</option>
                                                    <option data-country="id">Indonesia</option>
                                                    <option data-country="ir">Iran</option>
                                                    <option data-country="iq">Iraq</option>
                                                    <option data-country="ie">Ireland</option>
                                                    <option data-country="im">Isle of Man</option>
                                                    <option data-country="il">Israel</option>
                                                    <option data-country="it">Italy</option>
                                                    <option data-country="jm">Jamaica</option>
                                                    <option data-country="jp">Japan</option>
                                                    <option data-country="je">Jersey</option>
                                                    <option data-country="jo">Jordan</option>
                                                    <option data-country="kz">Kazakhstan</option>
                                                    <option data-country="ke">Kenya</option>
                                                    <option data-country="ki">Kiribati</option>
                                                    <option data-country="kp">North Korea</option>
                                                    <option data-country="kr">South Korea</option>
                                                    <option data-country="xk">Kosovo</option>
                                                    <option data-country="kw">Kuwait</option>
                                                    <option data-country="kg">Kyrgyzstan</option>
                                                    <option data-country="la">Laos</option>
                                                    <option data-country="lv">Latvia</option>
                                                    <option data-country="lb">Lebanon</option>
                                                    <option data-country="ls">Lesotho</option>
                                                    <option data-country="lr">Liberia</option>
                                                    <option data-country="ly">Libya</option>
                                                    <option data-country="li">Liechtenstein</option>
                                                    <option data-country="lt">Lithuania</option>
                                                    <option data-country="lu">Luxembourg</option>
                                                    <option data-country="mo">Macao</option>
                                                    <option data-country="mk">North Macedonia</option>
                                                    <option data-country="mg">Madagascar</option>
                                                    <option data-country="mw">Malawi</option>
                                                    <option data-country="my">Malaysia</option>
                                                    <option data-country="mv">Maldives</option>
                                                    <option data-country="ml">Mali</option>
                                                    <option data-country="mt">Malta</option>
                                                    <option data-country="mh">Marshall Islands</option>
                                                    <option data-country="mq">Martinique</option>
                                                    <option data-country="mr">Mauritania</option>
                                                    <option data-country="mu">Mauritius</option>
                                                    <option data-country="yt">Mayotte</option>
                                                    <option data-country="mx">Mexico</option>
                                                    <option data-country="fm">Micronesia</option>
                                                    <option data-country="md">Moldova</option>
                                                    <option data-country="mc">Monaco</option>
                                                    <option data-country="mn">Mongolia</option>
                                                    <option data-country="me">Montenegro</option>
                                                    <option data-country="ms">Montserrat</option>
                                                    <option data-country="ma">Morocco</option>
                                                    <option data-country="mz">Mozambique</option>
                                                    <option data-country="mm">Myanmar (Burma)</option>
                                                    <option data-country="na">Namibia</option>
                                                    <option data-country="nr">Nauru</option>
                                                    <option data-country="np">Nepal</option>
                                                    <option data-country="nl">Netherlands</option>
                                                    <option data-country="cu">Curaçao</option>
                                                    <option data-country="nc">New Caledonia</option>
                                                    <option data-country="nz">New Zealand</option>
                                                    <option data-country="ni">Nicaragua</option>
                                                    <option data-country="ne">Niger</option>
                                                    <option data-country="ng">Nigeria</option>
                                                    <option data-country="nu">Niue</option>
                                                    <option data-country="nf">Norfolk Island</option>
                                                    <option data-country="mp">Northern Mariana Islands</option>
                                                    <option data-country="no">Norway</option>
                                                    <option data-country="om">Oman</option>
                                                    <option data-country="pk">Pakistan</option>
                                                    <option data-country="pw">Palau</option>
                                                    <option data-country="ps">Palestine</option>
                                                    <option data-country="pa">Panama</option>
                                                    <option data-country="pg">Papua New Guinea</option>
                                                    <option data-country="py">Paraguay</option>
                                                    <option data-country="pe">Peru</option>
                                                    <option data-country="ph">Philippines</option>
                                                    <option data-country="pn">Pitcairn Islands</option>
                                                    <option data-country="pl">Poland</option>
                                                    <option data-country="pt">Portugal</option>
                                                    <option data-country="pr">Puerto Rico</option>
                                                    <option data-country="qa">Qatar</option>
                                                    <option data-country="re">Réunion</option>
                                                    <option data-country="ro">Romania</option>
                                                    <option data-country="ru">Russia</option>
                                                    <option data-country="rw">Rwanda</option>
                                                    <option data-country="bl">St. Barthélemy</option>
                                                    <option data-country="sh">St. Helena</option>
                                                    <option data-country="kn">St. Kitts & Nevis</option>
                                                    <option data-country="lc">St. Lucia</option>
                                                    <option data-country="mf">St. Martin</option>
                                                    <option data-country="pm">St. Pierre & Miquelon</option>
                                                    <option data-country="vc">St. Vincent & Grenadines</option>
                                                    <option data-country="ws">Samoa</option>
                                                    <option data-country="sm">San Marino</option>
                                                    <option data-country="st">São Tomé & Príncipe</option>
                                                    <option data-country="sa">Saudi Arabia</option>
                                                    <option data-country="sn">Senegal</option>
                                                    <option data-country="rs">Serbia</option>
                                                    <option data-country="sr">Serbia</option>
                                                    <option data-country="sc">Seychelles</option>
                                                    <option data-country="sl">Sierra Leone</option>
                                                    <option data-country="sg">Singapore</option>
                                                    <option data-country="sx">Sint Maarten</option>
                                                    <option data-country="sk">Slovakia</option>
                                                    <option data-country="si">Slovenia</option>
                                                    <option data-country="sb">Solomon Islands</option>
                                                    <option data-country="so">Somalia</option>
                                                    <option data-country="za">South Africa</option>
                                                    <option data-country="gs">South Georgia & South Sandwich Islands</option>
                                                    <option data-country="ss">South Sudan</option>
                                                    <option data-country="es">Spain</option>
                                                    <option data-country="lk">Sri Lanka</option>
                                                    <option data-country="sd">Sudan</option>
                                                    <option data-country="sr">Suriname</option>
                                                    <option data-country="sj">Svalbard & Jan Mayen</option>
                                                    <option data-country="sz">Eswatini</option>
                                                    <option data-country="se">Sweden</option>
                                                    <option data-country="ch">Switzerland</option>
                                                    <option data-country="sy">Syria</option>
                                                    <option data-country="tw">Taiwan</option>
                                                    <option data-country="tj">Tajikistan</option>
                                                    <option data-country="tz">Tanzania</option>
                                                    <option data-country="th">Thailand</option>
                                                    <option data-country="tl">Timor-Leste</option>
                                                    <option data-country="tg">Togo</option>
                                                    <option data-country="tk">Tokelau</option>
                                                    <option data-country="to">Tonga</option>
                                                    <option data-country="tt">Trinidad & Tobago</option>
                                                    <option data-country="tn">Tunisia</option>
                                                    <option data-country="tr">Turkey</option>
                                                    <option data-country="tm">Turkmenistan</option>
                                                    <option data-country="tc">Turks & Caicos Islands</option>
                                                    <option data-country="tv">Tuvalu</option>
                                                    <option data-country="ug">Uganda</option>
                                                    <option data-country="ua">Ukraine</option>
                                                    <option data-country="ae">United Arab Emirates</option>
                                                    <option data-country="gb">United Kingdom</option>
                                                    <option data-country="us" selected>United States</option>
                                                    <option data-country="um">U.S. Outlying Islands</option>
                                                    <option data-country="uy">Uruguay</option>
                                                    <option data-country="uz">Uzbekistan</option>
                                                    <option data-country="vu">Vanuatu</option>
                                                    <option data-country="ve">Venezuela</option>
                                                    <option data-country="vn">Vietnam</option>
                                                    <option data-country="vg">British Virgin Islands</option>
                                                    <option data-country="vi">U.S. Virgin Islands</option>
                                                    <option data-country="wf">Wallis & Futuna</option>
                                                    <option data-country="eh">Western Sahara</option>
                                                    <option data-country="ye">Yemen</option>
                                                    <option data-country="zm">Zambia</option>
                                                    <option data-country="zw">Zimbabwe</option>
                                                </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <x-ui.odoo-form-ui type="select" label="Currency" name="currency" :required="true" select2-selector="currency" :errorText="$errors->first('currency')">
                                                    <option data-currency="af">AFN - Afghan Afghani - ؋</option>
                                                    <option data-currency="al">ALL - Albanian Lek - Lek</option>
                                                    <option data-currency="dz">DZD - Algerian Dinar - دج</option>
                                                    <option data-currency="ao">AOA - Angolan Kwanza - Kz</option>
                                                    <option data-currency="ar">ARS - Argentine Peso - $</option>
                                                    <option data-currency="am">AMD - Armenian Dram - ֏</option>
                                                    <option data-currency="aw">AWG - Aruban Florin - ƒ</option>
                                                    <option data-currency="au">AUD - Australian Dollar - $</option>
                                                    <option data-currency="az">AZN - Azerbaijani Manat - m</option>
                                                    <option data-currency="bs">BSD - Bahamian Dollar - B$</option>
                                                    <option data-currency="bh">BHD - Bahraini Dinar - .د.ب</option>
                                                    <option data-currency="bd">BDT - Bangladeshi Taka - ৳</option>
                                                    <option data-currency="bb">BBD - Barbadian Dollar - Bds$</option>
                                                    <option data-currency="by">BYR - Belarusian Ruble - Br</option>
                                                    <option data-currency="be">BEF - Belgian Franc - fr</option>
                                                    <option data-currency="bz">BZD - Belize Dollar - $</option>
                                                    <option data-currency="bm">BMD - Bermudan Dollar - $</option>
                                                    <option data-currency="bt">BTN - Bhutanese Ngultrum - Nu.</option>
                                                    <option data-currency="bt">BTC - Bitcoin - ฿</option>
                                                    <option data-currency="bo">BOB - Bolivian Boliviano - Bs.</option>
                                                    <option data-currency="ba">BAM - Bosnia-Herzegovina Convertible Mark - KM</option>
                                                    <option data-currency="bw">BWP - Botswanan Pula - P</option>
                                                    <option data-currency="br">BRL - Brazilian Real - R$</option>
                                                    <option data-currency="gb">GBP - British Pound Sterling - £</option>
                                                    <option data-currency="bn">BND - Brunei Dollar - B$</option>
                                                    <option data-currency="bg">BGN - Bulgarian Lev - Лв.</option>
                                                    <option data-currency="bi">BIF - Burundian Franc - FBu</option>
                                                    <option data-currency="kh">KHR - Cambodian Riel - KHR</option>
                                                    <option data-currency="ca">CAD - Canadian Dollar - $</option>
                                                    <option data-currency="cv">CVE - Cape Verdean Escudo - $</option>
                                                    <option data-currency="ky">KYD - Cayman Islands Dollar - $</option>
                                                    <option data-currency="fr">XOF - CFA Franc BCEAO - CFA</option>
                                                    <option data-currency="fr">XAF - CFA Franc BEAC - FCFA</option>
                                                    <option data-currency="fr">XPF - CFP Franc - ₣</option>
                                                    <option data-currency="cl">CLP - Chilean Peso - $</option>
                                                    <option data-currency="cn">CNY - Chinese Yuan - ¥</option>
                                                    <option data-currency="co">COP - Colombian Peso - $</option>
                                                    <option data-currency="km">KMF - Comorian Franc - CF</option>
                                                    <option data-currency="cd">CDF - Congolese Franc - FC</option>
                                                    <option data-currency="cr">CRC - Costa Rican ColÃ³n - ₡</option>
                                                    <option data-currency="hr">HRK - Croatian Kuna - kn</option>
                                                    <option data-currency="cu">CUC - Cuban Convertible Peso - $, CUC</option>
                                                    <option data-currency="cz">CZK - Czech Republic Koruna - Kč</option>
                                                    <option data-currency="dk">DKK - Danish Krone - Kr.</option>
                                                    <option data-currency="dj">DJF - Djiboutian Franc - Fdj</option>
                                                    <option data-currency="do">DOP - Dominican Peso - $</option>
                                                    <option data-currency="bq">XCD - East Caribbean Dollar - $</option>
                                                    <option data-currency="eg">EGP - Egyptian Pound - ج.م</option>
                                                    <option data-currency="er">ERN - Eritrean Nakfa - Nfk</option>
                                                    <option data-currency="ee">EEK - Estonian Kroon - kr</option>
                                                    <option data-currency="et">ETB - Ethiopian Birr - Nkf</option>
                                                    <option data-currency="eu">EUR - Euro - €</option>
                                                    <option data-currency="fk">FKP - Falkland Islands Pound - £</option>
                                                    <option data-currency="fj">FJD - Fijian Dollar - FJ$</option>
                                                    <option data-currency="gm">GMD - Gambian Dalasi - D</option>
                                                    <option data-currency="ge">GEL - Georgian Lari - ლ</option>
                                                    <option data-currency="de">DEM - German Mark - DM</option>
                                                    <option data-currency="gh">GHS - Ghanaian Cedi - GH₵</option>
                                                    <option data-currency="gi">GIP - Gibraltar Pound - £</option>
                                                    <option data-currency="gr">GRD - Greek Drachma - ₯, Δρχ, Δρ</option>
                                                    <option data-currency="gt">GTQ - Guatemalan Quetzal - Q</option>
                                                    <option data-currency="gn">GNF - Guinean Franc - FG</option>
                                                    <option data-currency="gy">GYD - Guyanaese Dollar - $</option>
                                                    <option data-currency="ht">HTG - Haitian Gourde - G</option>
                                                    <option data-currency="hn">HNL - Honduran Lempira - L</option>
                                                    <option data-currency="hk">HKD - Hong Kong Dollar - $</option>
                                                    <option data-currency="hu">HUF - Hungarian Forint - Ft</option>
                                                    <option data-currency="is">ISK - Icelandic KrÃ³na - kr</option>
                                                    <option data-currency="in">INR - Indian Rupee - ₹</option>
                                                    <option data-currency="id">IDR - Indonesian Rupiah - Rp</option>
                                                    <option data-currency="ir">IRR - Iranian Rial - ﷼</option>
                                                    <option data-currency="iq">IQD - Iraqi Dinar - د.ع</option>
                                                    <option data-currency="il">ILS - Israeli New Sheqel - ₪</option>
                                                    <option data-currency="it">ITL - Italian Lira - L,£</option>
                                                    <option data-currency="jm">JMD - Jamaican Dollar - J$</option>
                                                    <option data-currency="jp">JPY - Japanese Yen - ¥</option>
                                                    <option data-currency="jo">JOD - Jordanian Dinar - ا.د</option>
                                                    <option data-currency="kz">KZT - Kazakhstani Tenge - лв</option>
                                                    <option data-currency="ke">KES - Kenyan Shilling - KSh</option>
                                                    <option data-currency="kw">KWD - Kuwaiti Dinar - ك.د</option>
                                                    <option data-currency="kg">KGS - Kyrgystani Som - лв</option>
                                                    <option data-currency="la">LAK - Laotian Kip - ₭</option>
                                                    <option data-currency="lv">LVL - Latvian Lats - Ls</option>
                                                    <option data-currency="lb">LBP - Lebanese Pound - £</option>
                                                    <option data-currency="ls">LSL - Lesotho Loti - L</option>
                                                    <option data-currency="lr">LRD - Liberian Dollar - $</option>
                                                    <option data-currency="ly">LYD - Libyan Dinar - د.ل</option>
                                                    <option data-currency="lt">LTL - Lithuanian Litas - Lt</option>
                                                    <option data-currency="mo">MOP - Macanese Pataca - $</option>
                                                    <option data-currency="mk">MKD - Macedonian Denar - ден</option>
                                                    <option data-currency="mg">MGA - Malagasy Ariary - Ar</option>
                                                    <option data-currency="mw">MWK - Malawian Kwacha - MK</option>
                                                    <option data-currency="my">MYR - Malaysian Ringgit - RM</option>
                                                    <option data-currency="mv">MVR - Maldivian Rufiyaa - Rf</option>
                                                    <option data-currency="mr">MRO - Mauritanian Ouguiya - MRU</option>
                                                    <option data-currency="mu">MUR - Mauritian Rupee - ₨</option>
                                                    <option data-currency="mx">MXN - Mexican Peso - $</option>
                                                    <option data-currency="md">MDL - Moldovan Leu - L</option>
                                                    <option data-currency="mn">MNT - Mongolian Tugrik - ₮</option>
                                                    <option data-currency="ma">MAD - Moroccan Dirham - MAD</option>
                                                    <option data-currency="mz">MZM - Mozambican Metical - MT</option>
                                                    <option data-currency="mm">MMK - Myanmar Kyat - K</option>
                                                    <option data-currency="na">NAD - Namibian Dollar - $</option>
                                                    <option data-currency="np">NPR - Nepalese Rupee - ₨</option>
                                                    <option data-currency="nl">ANG - Netherlands Antillean Guilder - ƒ</option>
                                                    <option data-currency="tw">TWD - New Taiwan Dollar - $</option>
                                                    <option data-currency="nz">NZD - New Zealand Dollar - $</option>
                                                    <option data-currency="ni">NIO - Nicaraguan CÃ³rdoba - C$</option>
                                                    <option data-currency="ng">NGN - Nigerian Naira - ₦</option>
                                                    <option data-currency="kp">KPW - North Korean Won - ₩</option>
                                                    <option data-currency="no">NOK - Norwegian Krone - kr</option>
                                                    <option data-currency="om">OMR - Omani Rial - .ع.ر</option>
                                                    <option data-currency="pk">PKR - Pakistani Rupee - ₨</option>
                                                    <option data-currency="pa">PAB - Panamanian Balboa - B/.</option>
                                                    <option data-currency="pg">PGK - Papua New Guinean Kina - K</option>
                                                    <option data-currency="py">PYG - Paraguayan Guarani - ₲</option>
                                                    <option data-currency="pe">PEN - Peruvian Nuevo Sol - S/.</option>
                                                    <option data-currency="ph">PHP - Philippine Peso - ₱</option>
                                                    <option data-currency="pl">PLN - Polish Zloty - zł</option>
                                                    <option data-currency="qa">QAR - Qatari Rial - ق.ر</option>
                                                    <option data-currency="ro">RON - Romanian Leu - lei</option>
                                                    <option data-currency="ru">RUB - Russian Ruble - ₽</option>
                                                    <option data-currency="rw">RWF - Rwandan Franc - FRw</option>
                                                    <option data-currency="sv">SVC - Salvadoran ColÃ³n - ₡</option>
                                                    <option data-currency="ws">WST - Samoan Tala - SAT</option>
                                                    <option data-currency="sa">SAR - Saudi Riyal - ﷼</option>
                                                    <option data-currency="sr">RSD - Serbian Dinar - din</option>
                                                    <option data-currency="sc">SCR - Seychellois Rupee - SRe</option>
                                                    <option data-currency="sl">SLL - Sierra Leonean Leone - Le</option>
                                                    <option data-currency="sg">SGD - Singapore Dollar - $</option>
                                                    <option data-currency="sk">SKK - Slovak Koruna - Sk</option>
                                                    <option data-currency="sb">SBD - Solomon Islands Dollar - Si$</option>
                                                    <option data-currency="so">SOS - Somali Shilling - Sh.so.</option>
                                                    <option data-currency="za">ZAR - South African Rand - R</option>
                                                    <option data-currency="kr">KRW - South Korean Won - ₩</option>
                                                    <option data-currency="lk">LKR - Sri Lankan Rupee - Rs</option>
                                                    <option data-currency="sh">SHP - St. Helena Pound - £</option>
                                                    <option data-currency="sd">SDG - Sudanese Pound - .س.ج</option>
                                                    <option data-currency="sr">SRD - Surinamese Dollar - $</option>
                                                    <option data-currency="sz">SZL - Swazi Lilangeni - E</option>
                                                    <option data-currency="se">SEK - Swedish Krona - kr</option>
                                                    <option data-currency="ch">CHF - Swiss Franc - CHf</option>
                                                    <option data-currency="sy">SYP - Syrian Pound - LS</option>
                                                    <option data-currency="st">STD - São Tomé and Príncipe Dobra - Db</option>
                                                    <option data-currency="tj">TJS - Tajikistani Somoni - SM</option>
                                                    <option data-currency="tz">TZS - Tanzanian Shilling - TSh</option>
                                                    <option data-currency="th">THB - Thai Baht - ฿</option>
                                                    <option data-currency="to">TOP - Tongan pa'anga - $</option>
                                                    <option data-currency="tt">TTD - Trinidad & Tobago Dollar - $</option>
                                                    <option data-currency="tn">TND - Tunisian Dinar - ت.د</option>
                                                    <option data-currency="tr">TRY - Turkish Lira - ₺</option>
                                                    <option data-currency="tm">TMT - Turkmenistani Manat - T</option>
                                                    <option data-currency="ug">UGX - Ugandan Shilling - USh</option>
                                                    <option data-currency="ua">UAH - Ukrainian Hryvnia - ₴</option>
                                                    <option data-currency="ae">AED - United Arab Emirates Dirham - إ.د</option>
                                                    <option data-currency="uy">UYU - Uruguayan Peso - $</option>
                                                    <option data-currency="us" selected>USD - US Dollar - $</option>
                                                    <option data-currency="uz">UZS - Uzbekistan Som - лв</option>
                                                    <option data-currency="vu">VUV - Vanuatu Vatu - VT</option>
                                                    <option data-currency="ve">VEF - Venezuelan BolÃ­var - Bs</option>
                                                    <option data-currency="vn">VND - Vietnamese Dong - ₫</option>
                                                    <option data-currency="ye">YER - Yemeni Rial - ﷼</option>
                                                    <option data-currency="zm">ZMK - Zambian Kwacha - ZK</option>
                                                </x-ui.odoo-form-ui>
                                            </div>
                                        </div>

                                        <!-- Row 7: City & State -->
                                        <div class="row mb-4">
                                            <div class="col-md-6 mb-3 mb-md-0">
                                                <x-ui.odoo-form-ui type="select" label="City" name="city" id="cityInput" :errorText="$errors->first('city')">
                                                    <option value="">Select City</option>
                                                </x-ui.odoo-form-ui>
                                            </div>
                                            <div class="col-md-6">
                                                <x-ui.odoo-form-ui type="select" label="State" name="state" id="stateInput" :errorText="$errors->first('state')">
                                                    <option value="">Select State</option>
                                                </x-ui.odoo-form-ui>
                                            </div>
                                        </div>

                                        <div class="row mb-4">
                                            <div class="col-md-6 mb-3 mb-md-0">
                                                <x-ui.odoo-form-ui type="select" label="Time Zone" name="time_zone" :required="true" select2-selector="tzone" :errorText="$errors->first('time_zone')">
                                                    <option data-tzone="feather-moon">(GMT -12:00) Eniwetok, Kwajalein</option>
                                                    <option data-tzone="feather-moon">(GMT -7:00) Mountain Time (US &amp; Canada)</option>
                                                    <option data-tzone="feather-moon">(GMT -6:00) Central Time (US &amp; Canada), Mexico City</option>
                                                    <option data-tzone="feather-moon">(GMT -5:00) Eastern Time (US &amp; Canada), Bogota, Lima</option>
                                                    <option data-tzone="feather-moon">(GMT -4:30) Caracas</option>
                                                    <option data-tzone="feather-moon">(GMT -4:00) Atlantic Time (Canada), Caracas, La Paz</option>
                                                    <option data-tzone="feather-moon">(GMT -3:30) Newfoundland</option>
                                                    <option data-tzone="feather-moon">(GMT -3:00) Brazil, Buenos Aires, Georgetown</option>
                                                    <option data-tzone="feather-moon">(GMT -2:00) Mid-Atlantic</option>
                                                    <option data-tzone="feather-moon">(GMT -1:00) Azores, Cape Verde Islands</option>
                                                    <option data-tzone="feather-sunrise" selected>(GMT) Western Europe Time, London, Lisbon, Casablanca</option>
                                                    <option data-tzone="feather-sun">(GMT +1:00) Brussels, Copenhagen, Madrid, Paris</option>
                                                    <option data-tzone="feather-sun">(GMT +2:00) Kaliningrad, South Africa</option>
                                                    <option data-tzone="feather-sun">(GMT +3:00) Baghdad, Riyadh, Moscow, St. Petersburg</option>
                                                    <option data-tzone="feather-sun">(GMT +3:30) Tehran</option>
                                                    <option data-tzone="feather-sun">(GMT +4:00) Abu Dhabi, Muscat, Baku, Tbilisi</option>
                                                    <option data-tzone="feather-sun">(GMT +4:30) Kabul</option>
                                                    <option data-tzone="feather-sun">(GMT +5:00) Ekaterinburg, Islamabad, Karachi, Tashkent</option>
                                                    <option data-tzone="feather-sun">(GMT +5:30) Bombay, Calcutta, Madras, New Delhi</option>
                                                    <option data-tzone="feather-sun">(GMT +5:45) Kathmandu, Pokhara</option>
                                                    <option data-tzone="feather-sun">(GMT +6:00) Almaty, Dhaka, Colombo</option>
                                                    <option data-tzone="feather-sun">(GMT +6:30) Yangon, Mandalay</option>
                                                    <option data-tzone="feather-sun">(GMT +7:00) Bangkok, Hanoi, Jakarta</option>
                                                    <option data-tzone="feather-sun">(GMT +8:00) Beijing, Perth, Singapore, Hong Kong</option>
                                                    <option data-tzone="feather-sun">(GMT +8:45) Eucla</option>
                                                    <option data-tzone="feather-sun">(GMT +9:00) Tokyo, Seoul, Osaka, Sapporo, Yakutsk</option>
                                                    <option data-tzone="feather-sun">(GMT +9:30) Adelaide, Darwin</option>
                                                    <option data-tzone="feather-sun">(GMT +10:00) Eastern Australia, Guam, Vladivostok</option>
                                                    <option data-tzone="feather-sun">(GMT +10:30) Lord Howe Island</option>
                                                    <option data-tzone="feather-sun">(GMT +11:00) Magadan, Solomon Islands, New Caledonia</option>
                                                    <option data-tzone="feather-sun">(GMT +11:30) Norfolk Island</option>
                                                    <option data-tzone="feather-sun">(GMT +12:00) Auckland, Wellington, Fiji, Kamchatka</option>
                                                    <option data-tzone="feather-sun">(GMT +12:45) Chatham Islands</option>
                                                    <option data-tzone="feather-sun">(GMT +13:00) Apia, Nukualofa</option>
                                                    <option data-tzone="feather-sun">(GMT +14:00) Line Islands, Tokelau</option>
                                                </x-ui.odoo-form-ui>
                                            </div>
                                            <div class="col-md-6">
                                                <x-ui.odoo-form-ui type="select" label="Status" name="status" select2-selector="status" :errorText="$errors->first('status')">
                                                        <option value="success" data-bg="bg-success" selected>Active</option>
                                                        <option value="warning" data-bg="bg-warning">Inactive</option>
                                                </x-ui.odoo-form-ui>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
@endsection

@push('scripts')
<script>
$(document).ready(function(){
    $(document).on("click", ".upload-button", function(){
        $(".file-upload").click();
    });

    $(document).on("change", ".file-upload", function(){
        var fileInput = this;
        if (fileInput.files && fileInput.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $(".upload-pic").attr("src", e.target.result);
            };
            reader.readAsDataURL(fileInput.files[0]);
        }
    });
});
</script>
@endpush

@push('scripts')
<script>
(function () {
    if (window.hrmsThemedValidationInstalled) return;
    window.hrmsThemedValidationInstalled = true;

    function fieldLabel(field) {
        const label = field.closest('.odoo-form-group')?.querySelector('.odoo-form-label');
        return label ? label.textContent.replace('*', '').trim().toLowerCase() : 'this field';
    }

    function fieldAnchor(field) {
        if (field.tagName === 'SELECT' && field.nextElementSibling?.classList.contains('select2-container')) {
            return field.nextElementSibling;
        }
        return field.type === 'radio' ? (field.closest('.odoo-form-group')?.querySelector('.flex-grow-1') || field) : field;
    }

    function showError(field) {
        field.classList.add('is-invalid');
        field.setAttribute('aria-invalid', 'true');
        const anchor = fieldAnchor(field);
        let error = anchor.nextElementSibling;
        if (!error || !error.classList.contains('hrms-client-validation-error')) {
            error = document.createElement('div');
            error.className = 'invalid-feedback d-block fs-11 mt-1 hrms-client-validation-error';
            anchor.insertAdjacentElement('afterend', error);
        }
        error.textContent = field.validity.valueMissing
            ? (field.tagName === 'SELECT' ? `Please select ${fieldLabel(field)}.` : `Please enter ${fieldLabel(field)}.`)
            : (field.validationMessage || 'Please enter a valid value.');
    }

    function clearError(field) {
        field.classList.remove('is-invalid');
        field.removeAttribute('aria-invalid');
        const error = fieldAnchor(field).nextElementSibling;
        if (error?.classList.contains('hrms-client-validation-error')) error.remove();
    }

    function requiredFields(form) {
        return Array.from(form.querySelectorAll('[required]')).filter(field => !field.disabled && field.type !== 'hidden');
    }

    function validate(field) {
        if (field.checkValidity()) {
            clearError(field);
            return true;
        }
        showError(field);
        return false;
    }

    function bind(root) {
        root.querySelectorAll('form').forEach(form => {
            if (form.dataset.hrmsThemedValidation === '1' || !form.querySelector('[required]')) return;
            form.dataset.hrmsThemedValidation = '1';
            form.setAttribute('novalidate', 'novalidate');
            requiredFields(form).forEach(field => {
                field.addEventListener('input', () => validate(field));
                field.addEventListener('change', () => validate(field));
            });
            form.addEventListener('submit', event => {
                const invalid = requiredFields(form).find(field => !validate(field));
                if (!invalid) return;
                event.preventDefault();
                event.stopImmediatePropagation();
                const target = invalid.tagName === 'SELECT' && invalid.nextElementSibling?.classList.contains('select2-container') ? invalid.nextElementSibling : invalid;
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                (target.querySelector?.('.select2-selection') || invalid).focus({ preventScroll: true });
            });
        });
    }

    document.addEventListener('DOMContentLoaded', () => bind(document));
})();
</script>
@endpush
