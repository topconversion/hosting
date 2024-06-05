$(function() {

    // Укажите ID Zero блоков со скроллом и стрелками
    var scrollBlocks = [
        { blockScrollId: '#rec754036968', blockArrowsId: '#rec754037611' },
        { blockScrollId: '#rec754778113', blockArrowsId: '#rec754793145' },
        { blockScrollId: '#rec754778113', blockArrowsId: '#rec754794077' },
        { blockScrollId: '#rec755223050', blockArrowsId: '#rec755222863' },
        { blockScrollId: '#rec755228578', blockArrowsId: '#rec755228577' },
        { blockScrollId: '#rec755228578', blockArrowsId: '#rec755228579' }
    ];
    
    var shiftSize;
    $(window).on('load resize', function(){
        
        if (window.matchMedia('(max-width: 480px)').matches) {
            // Укажите величину сдвига при клике на стрелку для разрешения 320-480px
            shiftSize = (window.screen.width) ?? '450px';
        }
        else if (window.matchMedia('(max-width: 640px)').matches) {
            // Укажите величину сдвига при клике на стрелку для разрешения 481-640px
            shiftSize = '480px';
        }
        else if (window.matchMedia('(max-width: 960px)').matches) {
            // Укажите величину сдвига при клике на стрелку для разрешения 641-960px
            shiftSize = '640px';
        }
        else if (window.matchMedia('(max-width: 1200px)').matches) {
            // Укажите величину сдвига при клике на стрелку для разрешения 961-1200px
            shiftSize = '960px';
        }
        else {
            // Укажите величину сдвига при клике на стрелку для разрешения больше 1200px
            shiftSize = '480px';
        }

    });
    
    function initScrollBooster(blockScrollId) {
        $(blockScrollId + ' .t396__artboard').addClass('scrollbooster-viewport').wrapInner('<div class="scrollbooster-content"></div>');
        $(blockScrollId + ' .t396').css('overflow','hidden');
    
        new ScrollBooster({
            viewport: $(blockScrollId + ' .scrollbooster-viewport')[0],
            content:  $(blockScrollId + ' .scrollbooster-content')[0],
            scrollMode: 'native',
            pointerMode: 'mouse',
            bounce: false,
            onPointerDown: function() { $(blockScrollId + ' *:focus').blur() }
        });
    }
    
    function bindArrows(blockArrowsId, blockScrollId) {
        $(blockArrowsId + ' .arrow-left').on('click', function(e) {
            e.preventDefault();
            $(blockScrollId + ' .t396__artboard').animate( { scrollLeft: '-=' + shiftSize }, 300);
        });

        $(blockArrowsId + ' .arrow-right').on('click', function(e) {
            e.preventDefault();
            $(blockScrollId + ' .t396__artboard').animate( { scrollLeft: '+=' + shiftSize }, 300);
        });
    }
    
    scrollBlocks.forEach(function(block) {
        initScrollBooster(block.blockScrollId);
        bindArrows(block.blockArrowsId, block.blockScrollId);
    });

});
