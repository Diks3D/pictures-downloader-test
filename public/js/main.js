$(function () {
    collection = [];
    var gallery = [];
    var margin = 10;
    
    function RowSet(){
        this.sumLength = 0;
        this.elLength = 0;
        this.elements = [];
        
        this.addElement = function(image){
            this.elements.push(image);
            this.elLength += image.width;
            this.sumLength = this.elLength + margin * (this.elements.length - 1);
        };
        
        this.removeLast = function(){
            var image = this.elements.pop();
            this.elLength -= image.width;
            this.sumLength = this.elLength + margin * (this.elements.length - 1);
            return image;
        };
    }
    
    function fillGallery(){
        var tmpCollection = collection;
        var rowNumber = 0;
        var rowLength = 0;
        var rows = [];
        var rowWidth = $('#images_gallery').innerWidth();
        
        do{
            var image = tmpCollection.shift();
            
            if(gallery.length === 0){
                gallery.push(new RowSet());
            } 
            
            for(var x in gallery){
                if(gallery[x].sumLength + image.width <= rowWidth){
                    gallery[x].addElement(image);
                }
            }
            
        } while(tmpCollection.length !== 0);
    }
    
    $('#upload_input').fileupload({
        dataType: 'json',
        done: function (e, data) {
            ans1 = e;
            ans2 = data;
            $.each(data.result.images, function (index, image) {
                $('<p/>').text(image.url).appendTo(document.body);
            });
        },
        error: function (e, status, errorMessage) {
            alert(e.responseJSON.message);
        }
    });
    
    $(document).ready(function(){
        $.getJSON('/', function(data){
            collection = data.images;
            fillGallery();
        });
    });
});