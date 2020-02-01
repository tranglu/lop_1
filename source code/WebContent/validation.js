	function kiemtranhap()
	{
		var inputTen = document.forms["form1"]["username"];
		//var inputTen = document.form1.username;
		var giaTriTen = inputTen.value;
		var theP = document.getElementById("thongbao");
		if (giaTriTen == ""){
			//alert("Nhập vào một giá trị");
			theP.style.display = "block";
			theP.innerHTML ="HÃY NHẬP VÀO GÍA TRỊ";
			theP.style.color = "red";
			return false;
		}else {
			//alert("Bạn đã nhập rồi! Thanks you");
			theP.style.display ="none";
			return true; 
		}
	}
	
	function kiemtraTatcaKitu(idTag) {
		var inputTag = document.getElementById(idTag);
		var letters = /^[A-Za-z]+$/;
		var theP = document.getElementById("thongbao1");
		if(inputTag.value.match(letters))
		{
			theP.style.display ="none";
			return true; 
		} else{
			theP.style.display = "block";
			theP.innerHTML ="HÃY NHẬP TẤT CẢ ĐỀU LÀ KÍ ";
			theP.style.color = "red";
			return false;
		}
			
	}
	
	function kiemtraTatcaLaSo(idTag) {
		var inputTag = document.getElementById(idTag);
		var numbers = /^[0-9]+$/;
		var theP = document.getElementById("thongbao1");
		if(inputTag.value.match(numbers))
		{
			theP.style.display ="none";
			return true; 
		} else{
			theP.style.display = "block";
			theP.innerHTML ="HÃY NHẬP TẤT CẢ ĐỀU LÀ SỐ ";
			theP.style.color = "red";
			return false;
		}
			
	}
	
	function kiemtraEmailHopLe(idTag) {
		var inputTag = document.getElementById(idTag);
		var email = /^([\w\.])+@([a-zA-Z0-9\-])+\.([a-zA-Z]{2,4})(\.[a-zA-Z]{2,4})?$/;
		var theP = document.getElementById("thongbao1");
		if(inputTag.value.match(email))
		{
			theP.style.display ="none";
			return true; 
		} else{
			theP.style.display = "block";
			theP.innerHTML ="HÃY NHẬP EMAIL HỢP LỆ ";
			theP.style.color = "red";
			return false;
		}
			
	}
	
	function kiemtraminmax(idText, minlength, maxlength)
	{ 
		var inputText = document.getElementById(idText);
		var field = inputText.value; 
		var theP = document.getElementById("thongbao1");
	
		if(field.length<minlength || field.length> maxlength){
			theP.style.display = "block";
			theP.innerHTML ="HÃY NHẬP vào giá trị từ  " + minlength +" đến " + maxlength;
			theP.style.color = "red";
			return false;
		} else { 
			//alert('Your userid have accepted.');
			theP.style.display ="none";
			return true;
		}

	}


	
	//Sư kiện khi nhấn tab đăng kí
	function onDangki(){
		// ẩn phần đăng nhập
		// hiển thị div đăng 
		var divDki = document.getElementById("divdangki");
		var divDangnhap = document.getElementById("divdangnhap");
		
		divDki.style.display ="block";
		divDangnhap.style.display ="none";
		
	}
	
	
	//Sư kiện khi nhấn tab đăng kí
	function onDangNhap(){
		// ẩn phần đăng kí
		// hiển thị div đăng 
		var divDki = document.getElementById("divdangki");
		var divDangnhap = document.getElementById("divdangnhap");
		
		divDki.style.display ="none";
		divDangnhap.style.display ="block";
		
	}
	
	function ktraBatBuocDangKi(){
		var pthongbao = document.getElementById ("pthongbao");
		var nghenghiep = document.getElementById ("nghenghiep");
		var nam = document.getElementById("nam");
		var nu = document.getElementById("nu");
		if(frmDangKi.tendangnhap.value == "" || frmDangKi.matkhau.value == "" 
			|| frmDangKi.email.value == "" || frmDangKi.tuoi.value ==""  ){
			frmDangKi.tendangnhap.style.border ="solid 2px red";
			frmDangKi.matkhau.style.border ="solid 2px red";
			frmDangKi.email.style.border ="solid 2px red";
			frmDangKi.tuoi.style.border ="solid 2px red";
			pthongbao.style.display ="block";
			
			pthongbao.innerHTML = "Bạn cần nhập dữ liệu cho các trường đầy đủ" +"<br/>";
			return false;
		} else if(nghenghiep.selectedIndex== 0) {
			pthongbao.style.display ="block";
			pthongbao.innerHTML = "Bạn phải chọn Nghề Nghiêp <br/>";
			return false;
		} else if(!nam.checked && !nu.checked){
			pthongbao.style.display ="block";
			pthongbao.innerHTML = "Bạn phải chọn Giới Tính <br/>";
			return false;
		} else {
			pthongbao.style.display ="none";
			return true;
		}
		
	}
	
	function kiemtrachieudaichuoi(idText, minlength, maxlength)
	{ 
		var inputText = document.getElementById(idText);
		var field = inputText.value; 
		var pthongbao = document.getElementById ("pthongbao");	
		if(field.length<minlength || field.length> maxlength){
			pthongbao.style.display = "block";
			pthongbao.innerHTML ="HÃY NHẬP vào giá trị từ  " + minlength +" đến " + maxlength;
			//theP.style.color = "red";
			return false;
		} else { 
			//alert('Your userid have accepted.');
			pthongbao.style.display ="none";
			return true;
		}

	}
	
	
	function kiemtraEmail(idTag) {
		var inputTag = document.getElementById(idTag);
		var pthongbao = document.getElementById ("pthongbao");	

		var email = /^([\w\.])+@([a-zA-Z0-9\-])+\.([a-zA-Z]{2,4})(\.[a-zA-Z]{2,4})?$/;
		//var theP = document.getElementById("thongbao1");
		if(inputTag.value.match(email))
		{
			pthongbao.style.display ="none";
			return true; 
		} else{
			pthongbao.style.display = "block";
			pthongbao.innerHTML ="HÃY NHẬP EMAIL HỢP LỆ ";
			//theP.style.color = "red";
			return false;
		}
			
	}
	function kiemtraTuoi(minTuoi, maxTuoi, idTagThongBao){
		var pthongbao = document.getElementById (idTagThongBao);	
		var tuoi = document.getElementById("tuoi");
		//var inputTag = document.getElementById(idTag);
		var numbers = /^[0-9]+$/;
		//var theP = document.getElementById("thongbao1");
		if(tuoi.value.match(numbers))
		{
			//pthongbao.style.display ="none";
			var intTuoi = parseInt(tuoi.value);
			if(intTuoi> maxTuoi || intTuoi < minTuoi){
				pthongbao.style.display = "block";
				pthongbao.innerHTML ="HÃY NHẬP TUỔI TỪ "+ minTuoi + "ĐẾN "+ maxTuoi;
				return false;
			}else {
				pthongbao.style.display = "none";
				return true; 
			} 
			
		} else{
			pthongbao.style.display = "block";
			pthongbao.innerHTML ="HÃY NHẬP TẤT CẢ ĐỀU LÀ SỐ ";
			//theP.style.color = "red";
			return false;
		}
	}
	
	function KiemTraHopLe(){
		return ktraBatBuocDangKi() && kiemtrachieudaichuoi("tendangnhap",1,30)
		&& kiemtraEmail("email") && kiemtraTuoi(18,40,"pthongbao");
	}