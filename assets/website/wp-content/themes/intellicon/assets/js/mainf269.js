/**
*
* --------------------------------------------------------------------
*
* Template : intellicon - AI & Machine Learning WordPress Theme

* --------------------------------------------------------------------
*
**/

(function($) {
    "use strict";
    // sticky menu
    var header = $('.menu-sticky');
    var win = $(window);
    var headerinnerHeight = $(".header-inner").innerHeight();

    win.on('scroll', function() {
       var scroll = win.scrollTop();
       if (scroll < headerinnerHeight) {
           header.removeClass("sticky");
           $('.menu-area').removeClass("sticky-menu");
           $('.header-inner').removeClass("tp-sticky");
           
       } else {
           header.addClass("sticky");
           $('.menu-area').addClass("sticky-menu");
           $('.header-inner').addClass("tp-sticky");
       }
    });

    $('.header-inner').waypoint('sticky', {
      offset: 0
    });

    $(".widget_nav_menu li a").filter(function(){
        return $.trim($(this).html()) == '';
    }).hide();

    // collapse hidden
    $(function(){ 
        var navMain = $(".navbar-collapse"); // avoid dependency on #id
         // when you have dropdown inside navbar
         navMain.on("click", "a:not([data-toggle])", null, function () {
             navMain.collapse('hide');
        });
     });

    // video 
    if ($('.player').length) {
        $(".player").YTPlayer();
    } 

    $('.video-btn').magnificPopup({
        type: 'iframe',
        callbacks: {
            
        }
    });

    $(".menu-area .navbar ul > li.menu-item-has-children").hover(
        function () {
            $(this).addClass('hover-minimize');
        },
        function () {
            $(this).removeClass("hover-minimize");
        }
    );

    $( ".showcase-item" ).hover(function() {
        $( this ).toggleClass("hover");
    });

     //Phone Number
    $('.phone_call').on('click', function(event) {        
        $('.phone_num_call').slideToggle('show');
    });

    //search 

     $('.sticky_search').on('click', function(event) {        
        $('.sticky_form').animate({ opacity: 'toggle' }, 500);;
        $( '.sticky_form input' ).focus();
    });

    $('.sticky_search').on('click', function() {
        $('body').removeClass('search-active').removeClass('search-close');
          if ($(this).hasClass('close-full')) {
            $('body').addClass('search-close');
             $('.sticky_form').fadeOut('show');
        }
        else {
            $('body').addClass('search-active');
        }
        return false;
    });
   
    $('.nav-link-container').on('click', function(e){
        $('body.on-offcanvas').removeClass('on-offcanvas');
        setTimeout(function(){ $('body').addClass('on-offcanvas'); },500);        
    });

    if($('.themephi-newsletter').hasClass('themephi-newsletters')){
        $('body').addClass('themephi-pages-btm-gap');
    } 
    $('.sticky_form_search').on('click', function() {      
        $('body, html').removeClass('themephi-search-active').removeClass('themephi-search-close');
          if ($(this).hasClass('close-search')) {
          $('body, html').addClass('themephi-search-close');

        }
        else {
          $('body, html').addClass('themephi-search-active');
        }
        return false;
    });

   
    if($('#themephi-header').hasClass('fixed-menu')){
        $('body').addClass('body-left-space');
    }  

    $("#themephi-header ul > li.classic").hover(
        function () {
            $('body').addClass('mega-classic');
        },
        function () {
            $('body.mega-classic').removeClass("mega-classic");
        }
    );

    var nav = $('#nav');
    if(nav.length){
        $('#menu-single-menu').onePageNav();
    }
   new WOW().init();

    $(document).ready(function(){
        function resizeNav() {
            $(".menu-ofcn").css({"height": window.innerHeight});
            var radius = Math.sqrt(Math.pow(window.innerHeight, 2) + Math.pow(window.innerWidth, 2));
            var diameter = radius * 2;
            $(".off-nav-layer").width(diameter);
            $(".off-nav-layer").height(diameter);
            $(".off-nav-layer").css({"margin-top": -radius, "margin-left": -radius});
        }
        $(".menu-button, .close-button").on('click', function() {
            $(".nav-toggle, .off-nav-layer, .menu-ofcn, .close-button, body").toggleClass("off-open");
        });    

        $(window).resize(resizeNav);
        resizeNav();
    });
   
    // Canvas Menu Js
    $( ".nav-link-container > a" ).off("click").on("click", function(event){
        event.preventDefault();
        $(".nav-link-container").toggleClass("nav-inactive-menu-link-container");
        $(".mobile-menus").toggleClass("nav-active-menu-container");
    });

    // menu last item
	$('.tps-default-header.header-style-1 .menu-area .menu_one .col-cell.menu-responsive.primary-menu ul#primary-menu-main > li').slice(-4).addClass('menu-last');

    // Get a quote popup
    $('.popup-quote').magnificPopup({
        type: 'inline',
        preloader: false,
        focus: '#qname',
        removalDelay: 500, //delay removal by X to allow out-animation
        // When elemened is focused, some mobile browsers in some cases zoom in
        // It looks not nice, so we disable it:
        callbacks: {
            beforeOpen: function() {
                this.st.mainClass = this.st.el.attr('data-effect');
                if($(window).width() < 700) {
                    this.st.focus = false;
                } else {
                    this.st.focus = '#qname';
                }
            }
        }
    });

    // Canvas Menu Js
    $( ".nav-link-container > a" ).off("click").on("click", function(event){
        event.preventDefault();
        $(".nav-link-container").toggleClass("nav-inactive-menu-link-container");
        $(".mobile-menus").toggleClass("nav-active-menu-container");
    });
    
    $(".nav-close-menu-li > a").on('click', function(event){
        $(".mobile-menus").toggleClass("nav-active-menu-container");
        $(".content").toggleClass("inactive-body");
    });


    $(function(){ 
        $( "ul.children" ).addClass( "sub-menu" );
    });
    
     //Videos popup jQuery activation code
    $('.popup-videos').magnificPopup({
        disableOn: 10,
        type: 'iframe',
        mainClass: 'mfp-fade',
        removalDelay: 160,
        preloader: false,
        fixedContentPos: false
    });

    // collapse hidden
    $(function(){ 
         var navMain = $(".navbar-collapse"); // avoid dependency on #id
         // "a:not([data-toggle])" - to avoid issues caused
         // when you have dropdown inside navbar
         navMain.on("click", "a:not([data-toggle])", null, function () {
             navMain.collapse('hide');
         });
     });

    //Select box wrap css
    $(".menu-area .navbar ul > li.mega > ul.sub-menu").wrapInner("<div class='container flex-mega'></div>");
    $('.menu-area .navbar ul > li.mega > ul.sub-menu li').first().addClass('first-li-item');


    if ($('div').hasClass('openingfoot')) {
        $('body').addClass('openingfootwrap');
    }

  
  //preloader
    $(window).on( 'load', function() {
        $("#intellicon-load").delay(500).fadeOut(200);
        $(".intellicon-loader").delay(500).fadeOut(200);       
        

    if($(window).width() < 992) {
      $('.themephi-menu').css('height', '0');
      $('.themephi-menu').css('opacity', '0');
      $('.themephi-menu').css('z-index', '-1');
      $('.themephi-menu-toggle').on( 'click', function(){
         $('.themephi-menu').css('opacity', '1');
         $('.themephi-menu').css('z-index', '1');
     });
    }
    })

    // JS for Filter
    $(window).load(function() {

    // image loaded portfolio init
    $('.grid').imagesLoaded(function() {
        $('.portfolio-filter').on('click', 'button', function() {
            var filterValue = $(this).attr('data-filter');
            $grid.isotope({
                filter: filterValue
            });
        });
        var $grid = $('.grid').isotope({
            animationOptions: {
             duration: 750,
             easing: 'linear',
             queue: false
           },

            itemSelector: '.grid-item',
            percentPosition: true,
            masonry: {
                columnWidth: '.grid-item',
            }
        });
    });
    $('.portfolio-filter button').on('click', function(event) {
        $(this).siblings('.active').removeClass('active');
        $(this).addClass('active');
        event.preventDefault();
    });

    });    
     // Counter Up  
    $('.rs-counter').counterUp({
        delay: 20,
        time: 1500
    });     
    // scrollTop init
    var win=$(window);
    var totop = $('#top-to-bottom');    
    win.on('scroll', function() {
        if (win.scrollTop() > 150) {
            totop.fadeIn();
        } else {
            totop.fadeOut();
        }
    });
    totop.on('click', function() {
        $("html,body").animate({
            scrollTop: 0
        }, 500)
    }); 

    $(function(){ 
        $( "ul.children" ).addClass( "sub-menu" );
    });  
    
    $( ".comment-body, .comment-respond" ).wrap( "<div class='comment-full'></div>" );    
    //mobile menu
    $.fn.menumaker = function(options) {      
        var mobile_menu = $(this), settings = $.extend({
          format: "dropdown",
          sticky: false
        }, options);
           return this.each(function() {
           mobile_menu.find('li ul').parent().addClass('has-sub');
           var multiTg = function() {
              mobile_menu.find(".has-sub").prepend('<span class="submenu-button"></span>');
              mobile_menu.find(".hash").parent().addClass('hash-has-sub');
              mobile_menu.find('.submenu-button').on('click', function() {
                  $(this).toggleClass('submenu-opened');
                  if ($(this).siblings('ul').hasClass('open-sub')) {
                      $(this).siblings('ul').removeClass('open-sub').hide('fadeToggle');
                      $(this).siblings('ul').hide('fadeToggle');                                     
                  }
                  else {
                      $(this).siblings('ul').addClass('open-sub').hide('fadeToggle');
                      $(this).siblings('ul').slideToggle().show('fadeToggle');
                  }
              });
           };
          if (settings.format === 'multitoggle') multiTg();
          else mobile_menu.addClass('dropdown');
          if (settings.sticky === true) mobile_menu.css('position', 'fixed');
          var resizeFix = function() {
               if ($( window ).width() > 991) {
                   mobile_menu.find('ul').show('fadeToggle');
                   mobile_menu.find('ul.sub-menu').hide('fadeToggle');
               }          
           };
          resizeFix();
          return $(window).on('resize', resizeFix);
          });
      };
      $(document).ready(function(){
        $("#mobile_menu").menumaker({
           format: "multitoggle"
        });
        
    });

})(jQuery);